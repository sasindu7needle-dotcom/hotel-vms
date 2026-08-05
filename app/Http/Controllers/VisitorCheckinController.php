<?php

namespace App\Http\Controllers;

use App\Services\GeminiDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisitorCheckinController extends Controller
{
    /**
     * Verify an identity document using Gemini multimodal extraction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyVision(Request $request, GeminiDocumentService $gemini)
    {
        @set_time_limit(120);

        $request->validate([
            'document_type' => 'required|in:nic,driving_license,passport',
            'document_front_image' => 'required|file|image|mimes:jpeg,png,jpg,webp|max:10240',
            'document_back_image' => 'required_if:document_type,nic|nullable|file|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $docType = $request->string('document_type')->toString();
        $file = $request->file('document_front_image');
        // Only the NIC flow uses the reverse side. Passports and driving
        // licences are verified from their portrait/details page.
        $backFile = $docType === 'nic' ? $request->file('document_back_image') : null;

        $imageBytes = file_get_contents($file->getRealPath());
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $backImageBytes = $backFile ? file_get_contents($backFile->getRealPath()) : null;
        $provider = 'google_gemini';
        try {
            $parsed = $gemini->extract(
                $file->getRealPath(),
                $mime,
                $backFile?->getRealPath(),
                $backFile?->getMimeType(),
                false,
                $docType,
            );
        } catch (\Throwable $exception) {
            logger()->warning('Gemini document extraction failed: '.$exception->getMessage().'. Attempting fallback to local Tesseract OCR...');

            try {
                $tesseract = app(\App\Services\TesseractOcrService::class);
                $frontOcr = $tesseract->extractLanguageTexts($file->getRealPath());
                $backOcr = $backFile ? $tesseract->extractLanguageTexts($backFile->getRealPath()) : [];

                $combinedMultilingual = trim(($frontOcr['combined'] ?? '')."\n".($backOcr['combined'] ?? ''));
                $combinedEnglish = trim(($frontOcr['eng'] ?? '')."\n".($backOcr['eng'] ?? ''));
                $combinedNative = trim(($frontOcr['sin'] ?? '')."\n".($frontOcr['tam'] ?? '')."\n".($backOcr['sin'] ?? '')."\n".($backOcr['tam'] ?? ''));

                if (filled($combinedMultilingual) || filled($combinedEnglish)) {
                    $parsed = $this->combineTesseractIdentityFields($combinedMultilingual, $combinedEnglish, $docType, $combinedNative);
                    $parsed = $this->translateIdentityFieldsToEnglish($parsed);
                    $provider = 'tesseract_ocr_fallback';
                } else {
                    throw $exception;
                }
            } catch (\Throwable $fallbackException) {
                return response()->json([
                    'success' => false,
                    'error' => str_contains(strtolower($exception->getMessage()), 'api key')
                        ? 'Gemini API is not configured on this server.'
                        : 'Gemini could not read the document. Please retry with clear, glare-free photos.',
                    'code' => 'gemini_extraction_failed',
                ], str_contains(strtolower($exception->getMessage()), 'api key') ? 503 : 502);
            }
        }

        $parsed['full_name_latin'] = $this->containsSinhalaOrTamil((string) data_get($parsed, 'full_name'))
            ? ''
            : (string) data_get($parsed, 'full_name', '');
        $parsed['address_latin'] = $this->containsSinhalaOrTamil((string) data_get($parsed, 'address'))
            ? ''
            : (string) data_get($parsed, 'address', '');
        if ($docType === 'nic') {
            $parsed['document_number'] = $this->normalizeDocumentNumber((string) data_get($parsed, 'document_number', ''), $docType);
        } elseif ($docType === 'driving_license') {
            // Sri Lankan driving licences print the licence number at field 5
            // and the holder's NIC at field 4c. Registration needs the NIC.
            $parsed['document_number'] = (string) data_get($parsed, 'nic_number', '');
        }

        $parsed['document_number'] = $this->normalizeDocumentNumber((string) data_get($parsed, 'document_number'), $docType);

        if ((int) data_get($parsed, 'confidence', 100) < 20) {
            Log::info('Gemini reported low document readability; applying structural validation.', [
                'document_type' => $docType,
                'confidence' => (int) data_get($parsed, 'confidence', 0),
                'document_number' => $this->maskDocumentNumber((string) data_get($parsed, 'document_number')),
            ]);
        }

        $missingFields = collect([
            'document number' => $this->isPlausibleDocumentNumber((string) data_get($parsed, 'document_number'), $docType),
            'full name' => $this->isPlausibleIdentityField((string) data_get($parsed, 'full_name'), 'name'),
            'address' => $this->isPlausibleIdentityField((string) data_get($parsed, 'address'), 'address'),
        ])->filter(fn ($isValid) => ! $isValid)->keys()->values()->all();

        if ($missingFields !== []) {
            $documentNumber = (string) data_get($parsed, 'document_number');
            $reason = blank($documentNumber)
                ? 'no_document_number'
                : (! $this->isPlausibleDocumentNumber($documentNumber, $docType) ? 'invalid_document_number' : 'incomplete_identity_fields');
            Log::warning('Document verification rejected.', [
                'document_type' => $docType,
                'json_decoded' => (bool) data_get($parsed, '_gemini_json_decoded', true),
                'document_number_key' => data_get($parsed, '_gemini_document_number_key'),
                'document_number_valid' => $this->isPlausibleDocumentNumber($documentNumber, $docType),
                'document_number' => $this->maskDocumentNumber($documentNumber),
                'reason' => $reason,
            ]);

            if (! data_get($parsed, '_gemini_json_decoded', true) && blank($documentNumber)) {
                return response()->json([
                    'success' => false,
                    'error' => 'The document reader returned an unreadable response. Please try again with clear, glare-free photos.',
                    'code' => 'invalid_gemini_response',
                ], 422);
            }

            if (blank($documentNumber)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No document number was detected. Retake the document photos closer, avoid glare, and keep the card edges visible.',
                    'code' => 'document_number_not_detected',
                ], 422);
            }

            if (! $this->isPlausibleDocumentNumber($documentNumber, $docType)) {
                return response()->json([
                    'success' => false,
                    'error' => 'The document number was detected but is not valid for this document type. Please retake clear photos of the original document.',
                    'code' => 'invalid_document_number',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'error' => 'Document extraction could not confidently read the '.implode(', ', $missingFields).'. Retake the document photos closer, avoid glare, and keep the card edges visible.',
                'code' => 'incomplete_identity_fields',
            ], 422);
        }

        $verificationId = (string) Str::uuid();
        $photoPath = "verified-visitors/{$verificationId}.{$extension}";
        Storage::disk('local')->put($photoPath, $imageBytes);

        $backPhotoPath = null;
        $backPhotoMime = null;
        if ($backFile && $backImageBytes !== null) {
            $backExtension = $backFile->getClientOriginalExtension() ?: 'jpg';
            $backPhotoPath = "verified-visitors/{$verificationId}-back.{$backExtension}";
            $backPhotoMime = $backFile->getMimeType() ?: 'image/jpeg';
            Storage::disk('local')->put($backPhotoPath, $backImageBytes);
        }

        $verification = [
            'session_id' => $verificationId,
            'verification_id' => $verificationId,
            'document_type' => $docType,
            'verified_at' => now()->toIso8601String(),
            'full_name' => $parsed['full_name'],
            'full_name_latin' => $parsed['full_name_latin'],
            'full_name_original' => data_get($parsed, 'full_name_original'),
            'document_number' => $parsed['document_number'],
            'address' => $parsed['address'],
            'address_latin' => $parsed['address_latin'],
            'address_original' => data_get($parsed, 'address_original'),
            'photo_url' => route('visitor.session_photo', ['type' => 'photo']),
            'photo_path' => $photoPath,
            'photo_mime' => $mime,
            'back_photo_path' => $backPhotoPath,
            'back_photo_mime' => $backPhotoMime,
            'ocr_text' => '',
            'ocr_confidence' => null,
            'provider' => $provider,
            'photo_capture_status' => 'pending',
        ];

        $request->session()->put('verification', $verification);
        $request->session()->put('didit_verification', $verification);
        $request->session()->save();

        return response()->json([
            'success' => true,
            'verification_id' => $verificationId,
            'redirect_url' => route('visitor.photo_capture'),
            'data' => $verification,
        ]);
    }

    /** Store the visitor photo captured by the camera. */
    public function capturePhoto(Request $request)
    {
        $verification = $request->session()->get('verification', []);
        if (! is_array($verification) || blank(data_get($verification, 'photo_path'))) {
            return response()->json(['error' => 'The document verification session has expired. Please upload the document again.'], 422);
        }

        $request->validate([
            'selfie' => 'required|file|image|mimes:jpeg,png,jpg,webp|max:6144',
        ]);

        $file = $request->file('selfie');
        $bytes = file_get_contents($file->getRealPath());

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $selfiePath = 'verified-visitors/'.data_get($verification, 'verification_id').'-photo.'.$extension;
        Storage::disk('local')->put($selfiePath, $bytes);
        if (! Storage::disk('local')->exists($selfiePath)) {
            return response()->json([
                'success' => false,
                'error' => 'The captured photo could not be stored. Please try again.',
            ], 500);
        }

        $request->session()->put('verification', array_merge($verification, [
            'selfie_path' => $selfiePath,
            'selfie_mime' => $file->getMimeType() ?: 'image/jpeg',
            'photo_capture_status' => 'completed',
            'photo_captured_at' => now()->toIso8601String(),
        ]));
        $request->session()->save();

        return response()->json([
            'success' => true,
            'redirect_url' => route('visitor.create', ['type' => data_get($verification, 'document_type', 'nic')]),
        ]);
    }

    /**
     * Generate OAuth2 access token for Google Cloud Service Account.
     */
    private function getAccessToken(): ?string
    {
        $credsPath = config('services.google_vision.credentials_path');

        if (blank($credsPath)) {
            return null;
        }

        if (! file_exists($credsPath)) {
            $candidate = base_path($credsPath);
            if (file_exists($candidate)) {
                $credsPath = $candidate;
            } else {
                return null;
            }
        }

        try {
            return Cache::remember('google_vision_access_token', 3300, function () use ($credsPath) {
                $json = json_decode(file_get_contents($credsPath), true);
                if (! is_array($json)) {
                    return null;
                }

                $clientEmail = data_get($json, 'client_email');
                $privateKey = data_get($json, 'private_key');
                $tokenUri = data_get($json, 'token_uri', 'https://oauth2.googleapis.com/token');

                if (blank($clientEmail) || blank($privateKey)) {
                    return null;
                }

                $now = time();
                $header = ['alg' => 'RS256', 'typ' => 'JWT'];
                $payload = [
                    'iss' => $clientEmail,
                    'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                    'aud' => $tokenUri,
                    'exp' => $now + 3600,
                    'iat' => $now,
                ];

                $base64UrlHeader = Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
                $base64UrlPayload = Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

                $signatureInput = "{$base64UrlHeader}.{$base64UrlPayload}";
                $signature = '';

                if (! openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
                    return null;
                }

                $base64UrlSignature = Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
                $jwt = "{$signatureInput}.{$base64UrlSignature}";

                $http = $this->withGoogleCertificate(Http::asForm());
                $response = $http->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                return $response->successful() ? $response->json('access_token') : null;
            });
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function withGoogleCertificate($http)
    {
        $caBundle = config('services.google_vision.ca_bundle');
        if (filled($caBundle) && file_exists($caBundle)) {
            return $http->withOptions(['verify' => $caBundle]);
        }

        return $http;
    }

    /**
     * Extract text from a local image file using the OCR.space Free API.
     *
     * Free tier: 25,000 requests/month — no payment required.
     * Get a free key at https://ocr.space/ocrapi/freekey
     * Uses modern AI/ML models that handle ID cards far better than Tesseract.
     */
    private function ocrSpaceExtract(string $filePath, string $apiKey): string
    {
        if (! file_exists($filePath) || blank($apiKey)) {
            return '';
        }

        $imageData = base64_encode((string) file_get_contents($filePath));
        $mime = mime_content_type($filePath) ?: 'image/jpeg';

        $response = Http::timeout(20)
            ->connectTimeout(5)
            ->withHeaders([
                'apikey' => $apiKey,
            ])
            ->asForm()
            ->post('https://api.ocr.space/parse/image', [
                'base64Image' => "data:{$mime};base64,{$imageData}",
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale' => 'true',
                'OCREngine' => '2',  // Engine 2 is better for complex documents
            ]);

        if (! $response->successful()) {
            logger()->info('OCR.space HTTP error: '.$response->status());
            return '';
        }

        $data = $response->json();
        $isErrored = data_get($data, 'IsErroredOnProcessing', false);
        if ($isErrored) {
            $errorMessage = data_get($data, 'ErrorMessage.0', 'Unknown error');
            logger()->info('OCR.space processing error: '.$errorMessage);
            return '';
        }

        $parsedResults = data_get($data, 'ParsedResults', []);
        $text = '';
        foreach ($parsedResults as $result) {
            $text .= data_get($result, 'ParsedText', '')."\n";
        }

        return trim($text);
    }

    /** Use an explicit CA bundle when the local PHP installation has none. */
    private function withTrustedCertificate($http)
    {
        $candidates = array_filter([
            config('services.google_vision.ca_bundle'),
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
            'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate)) {
                return $http->withOptions(['verify' => $candidate]);
            }
        }

        return $http;
    }

    /** Prefer native-script Tesseract fields so Google can translate the clean source text. */
    private function combineTesseractIdentityFields(
        string $multilingualText,
        string $englishText,
        string $docType,
        ?string $nativeText = null
    ): array {
        $multilingual = $this->parseDocumentText($multilingualText, $docType);
        $english = $this->parseDocumentText($englishText, $docType);
        $native = $this->extractNativeIdentityFields($nativeText ?: $multilingualText);

        $name = data_get($native, 'full_name')
            ?: data_get($english, 'full_name')
            ?: data_get($multilingual, 'full_name', '');
        $address = data_get($native, 'address')
            ?: data_get($english, 'address')
            ?: data_get($multilingual, 'address', '');

        return [
            'document_number' => data_get($english, 'document_number')
                ?: data_get($multilingual, 'document_number', ''),
            'full_name' => trim((string) $name),
            'full_name_latin' => $this->containsSinhalaOrTamil((string) $name)
                ? ''
                : trim((string) $name),
            'full_name_original' => data_get($native, 'full_name'),
            'address' => trim((string) $address),
            'address_latin' => $this->containsSinhalaOrTamil((string) $address)
                ? ''
                : trim((string) $address),
            'address_original' => data_get($native, 'address'),
        ];
    }

    private function bestNativeOcrText(array $result): string
    {
        return (string) collect(['sin', 'tam'])
            ->map(function ($language) use ($result) {
                $text = (string) data_get($result, $language, '');
                $fields = $this->extractNativeIdentityFields($text);
                $name = (string) data_get($fields, 'full_name', '');
                $address = (string) data_get($fields, 'address', '');
                $fieldScore = $this->nativeLetterCount($name)
                    + $this->nativeLetterCount($address)
                    + (filled($name) ? 25 : 0)
                    + (preg_match('/\d{1,4}\s*[,\/\-]?/u', $address) === 1 ? 35 : 0);
                $symbolPenalty = mb_strlen((string) preg_replace(
                    '/[\p{L}\p{M}\p{N}\s,.\/\-\x{200C}\x{200D}]/u',
                    '',
                    $name.' '.$address
                )) * 4;

                return [
                    'text' => $text,
                    // OCR confidence is deliberately secondary: malformed text can
                    // still receive a high character-level Tesseract confidence.
                    'score' => $fieldScore - $symbolPenalty
                        + ((float) data_get($result, $language.'_confidence', 0) * 0.15),
                ];
            })
            ->sortByDesc('score')
            ->first()['text'];
    }

    /** Read only Sinhala/Tamil name and address values from multilingual OCR. */
    private function extractNativeIdentityFields(string $ocrText): array
    {
        $lines = collect(preg_split('/\R/u', $ocrText) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => filled($line))
            ->values();

        $name = $this->extractLabeledBlock($lines, [
            'සම්පූර්ණ නම', 'නම', 'මுழுப் பெயர்', 'முழு பெயர்', 'பெயர்',
        ], 3);
        $address = $this->extractLabeledBlock($lines, [
            'ස්ථිර ලිපිනය', 'ලිපිනය', 'நிரந்தர முகவரி', 'வசிப்பிட முகவரி', 'முகவரி', 'விலாசம்',
        ], 5);

        if (filled($name) && ! $this->containsSinhalaOrTamil($name)) {
            $name = '';
        }
        if (filled($address) && ! $this->containsSinhalaOrTamil($address)) {
            $address = '';
        }

        if (blank($name) || $this->nativeLetterCount($name) < 5) {
            $name = $this->bestNativeLineBlock($lines, 'name');
        }

        // Address labels are frequently degraded into a short native word and can
        // accidentally consume the DOB/name lines that follow. A line beginning
        // with a house number is a materially stronger signal on Sri Lankan NICs.
        $scoredAddress = $this->bestNativeLineBlock($lines, 'address');
        if (filled($scoredAddress)) {
            $address = $scoredAddress;
        }

        return [
            'full_name' => $this->cleanNativeField($name, 'name'),
            'address' => $this->cleanNativeField($address, 'address'),
        ];
    }

    private function bestNativeLineBlock($lines, string $field): string
    {
        $scored = $lines->map(function ($line, $index) use ($field) {
            $cleaned = $this->cleanNativeField((string) $line, $field);

            return [
                'index' => $index,
                'line' => $cleaned,
                'score' => $this->scoreNativeFieldLine($cleaned, $field),
            ];
        })->filter(fn ($candidate) => $candidate['score'] > 0)->values();

        if ($scored->isEmpty()) {
            return '';
        }

        $best = $scored->sortByDesc('score')->first();
        $minimumAdjacentScore = max(12, $best['score'] * 0.45);
        $selected = $scored
            ->filter(fn ($candidate) => abs($candidate['index'] - $best['index']) <= 1
                && $candidate['score'] >= $minimumAdjacentScore
                && ($field !== 'address' || $this->nativeLetterCount($candidate['line']) >= 6)
                && $this->sameNativeScript($candidate['line'], $best['line']))
            ->sortBy('index')
            ->pluck('line')
            ->unique()
            ->map(fn ($line) => $this->cleanNativeField((string) $line, $field))
            ->filter(fn ($line) => filled($line))
            ->values()
            ->all();

        return trim(implode($field === 'address' ? ', ' : ' ', $selected ?: [$best['line']]));
    }

    private function scoreNativeFieldLine(string $line, string $field): float
    {
        if (! $this->containsSinhalaOrTamil($line)) {
            return 0;
        }

        $letters = $this->nativeLetterCount($line);
        if ($letters < 4) {
            return 0;
        }
        preg_match_all('/[\p{L}\x{200C}\x{200D}]{2,}/u', $line, $words);
        preg_match_all('/[\p{L}\x{200C}\x{200D}]{5,}/u', $line, $longWords);
        $digitCount = preg_match_all('/\d/u', $line);
        $symbolCount = mb_strlen((string) preg_replace('/[\p{L}\p{N}\s,\.\/\-\x{200C}\x{200D}]/u', '', $line));
        $score = $letters
            + (count($words[0] ?? []) * 3)
            + (count($longWords[0] ?? []) * 5)
            - ($symbolCount * 3);

        if ($field === 'name') {
            if ($digitCount > 6 || preg_match('/ලිපිනය|පාර|මාවත|முகவரி|விலாசம்|வீதி|சாலை/u', $line)) {
                return 0;
            }
            $score -= $digitCount * 8;
            if (preg_match('/ගේ|නායක|සේලා|நாயக்க|சேலா|குமார்|சிவா/u', $line)) {
                $score += 20;
            }
        } else {
            $hasAddressKeyword = preg_match('/අංක|පාර|මාවත|කොළඹ|ලිපිනය|இல|வீதி|சாலை|கொழும்பு|யாழ்ப்பாணம்|முகவரி|விலாசம்/u', $line) === 1;
            $startsLikeAddress = preg_match(
                '/^[^\p{N}]{0,5}\p{N}{1,4}(?:\s*[,\/\-]|\s+[\p{L}\p{M}]{1,4}\s+\p{N}{1,4}\s*[,\/\-])/u',
                trim($line)
            ) === 1;
            if (! $hasAddressKeyword && ! $startsLikeAddress) {
                return 0;
            }
            if ($digitCount > 0) {
                $score += 18;
            }
            $score += substr_count($line, ',') * 20;
            if ($hasAddressKeyword) {
                $score += 25;
            }
            if ($digitCount === 0 && ! preg_match('/පාර|මාවත|வீதி|சாலை|முகவரி|விலாசம்/u', $line)) {
                $score -= 15;
            }
        }

        return max(0, $score);
    }

    private function cleanNativeField(string $value, string $field): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value), " \t\n\r\0\x0B,.;:|_-~");
        if ($field === 'name') {
            $value = (string) preg_replace('/^(?:නම[ි]?|பெயர்)\s*[:.\-]?\s*/u', '', $value);
            $value = (string) preg_replace('/^\s*\d+\s*[^\p{L}\p{M}]*\s*/u', '', $value);
            $value = (string) preg_replace('/^[\p{L}\p{M}]{1,4}\s*-\s*/u', '', $value);
            $value = (string) preg_replace('/\s+\d{2,}\s*[.\s]*[\p{L}\p{M}]{0,4}\s*$/u', '', $value);
            $value = (string) preg_replace('/(?:\s+\d+\s*[\p{L}\p{M}]?[.\s]*)+$/u', '', $value);
        } else {
            $value = (string) preg_replace('/^(?:ස්ථිර\s+ලිපිනය|ලිපිනය|நிரந்தர\s+முகவரி|முகவரி|விலாசம்)\s*[:.\-]?\s*/u', '', $value);
            $value = (string) preg_replace('/^\s*\d?\s*(?:ලි|இல)\s+(?=\d)/u', '', $value);
            $value = (string) preg_replace('/\s+[\p{L}\p{M}]{1,2}\s+\d+\s*$/u', '', $value);
        }

        return trim($value, " \t\n\r\0\x0B,.;:|_-~");
    }

    private function nativeLetterCount(string $value): int
    {
        return preg_match_all('/[\x{0B80}-\x{0BFF}\x{0D80}-\x{0DFF}]/u', $value);
    }

    private function sameNativeScript(string $left, string $right): bool
    {
        $leftTamil = preg_match('/[\x{0B80}-\x{0BFF}]/u', $left) === 1;
        $rightTamil = preg_match('/[\x{0B80}-\x{0BFF}]/u', $right) === 1;

        return $leftTamil === $rightTamil;
    }

    private function isPlausibleDocumentNumber(string $value, string $docType): bool
    {
        $value = $this->normalizeDocumentNumber($value, $docType);
        if (in_array($docType, ['nic', 'driving_license'], true)) {
            return preg_match('/^(?:\d{9}[VX]|\d{12})$/', $value) === 1;
        }

        return preg_match('/^[A-Z0-9]{7,12}$/', $value) === 1;
    }

    private function normalizeDocumentNumber(string $value, string $docType): string
    {
        $value = strtoupper(trim($value));

        return in_array($docType, ['nic', 'driving_license'], true)
            ? (string) preg_replace('/[^0-9VX]/', '', $value)
            : (string) preg_replace('/[^A-Z0-9]/', '', $value);
    }

    private function maskDocumentNumber(string $value): string
    {
        $value = preg_replace('/\s+/', '', $value);

        return $value === '' ? '' : str_repeat('*', max(0, strlen($value) - 3)).substr($value, -3);
    }

    private function isPlausibleIdentityField(string $value, string $field): bool
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        $length = mb_strlen($value);
        if ($length < ($field === 'name' ? 5 : 8)
            || $length > ($field === 'name' ? 180 : 400)
            || preg_match('/[\{\}\[\]=<>|\\\\]/u', $value)) {
            return false;
        }

        if ($this->containsSinhalaOrTamil($value)) {
            $nativeCharacters = $this->nativeLetterCount($value);

            return $nativeCharacters >= ($field === 'name' ? 4 : 6)
                && ($field === 'name' || preg_match('/\p{N}|[\p{L}\p{M}]{3,}/u', $value) === 1);
        }

        $allowed = mb_strlen((string) preg_replace('/[^\p{Latin}\p{N}\s,.\'\/\-#()]/u', '', $value));
        if ($allowed / max(1, $length) < 0.90) {
            return false;
        }

        preg_match_all('/\p{Latin}{2,}/u', $value, $words);
        $wordCount = count($words[0] ?? []);
        if ($field === 'name') {
            return $wordCount >= 2 && $wordCount <= 18
                && preg_match('/\d/u', $value) !== 1;
        }

        return $wordCount >= 1 && preg_match('/\p{N}|\p{Latin}{3,}/u', $value) === 1;
    }

    private function parseDocumentText(string $ocrText, string $docType): array
    {
        $lines = collect(explode("\n", $ocrText))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => filled($line))
            ->values();

        $docNumber = $this->extractDocumentNumber($ocrText, $lines, $docType);

        // Extract English/Latin text first (Sri Lankan NICs always print English)
        $latinName = $this->extractLatinName($lines, $docType);
        $latinAddress = $this->extractLatinAddress($lines);

        // Fall back to generic extraction (may return native script)
        $fullName = $this->extractName($lines, $docType);
        $address = $this->extractAddress($lines, $docType);

        $nativeName = $this->containsSinhalaOrTamil($fullName) ? $fullName : null;
        $nativeAddress = $this->containsSinhalaOrTamil($address) ? $address : null;

        // If generic extraction returned English, use it as Latin too
        if (! $nativeName) {
            $latinName = filled($latinName) ? $latinName : $fullName;
        }
        if (! $nativeAddress) {
            $latinAddress = filled($latinAddress) ? $latinAddress : $address;
        }

        // PREFER English/Latin text when available (the card has it printed)
        return [
            'document_number' => $docNumber,
            'full_name' => filled($latinName) ? $latinName : ($nativeName ?: ''),
            'full_name_latin' => $latinName,
            'address' => filled($latinAddress) ? $latinAddress : ($nativeAddress ?: ''),
            'address_latin' => $latinAddress,
        ];
    }

    /** Translate native-script identity fields before they reach registration. */
    private function translateIdentityFieldsToEnglish(array $parsed): array
    {
        $fieldMap = [
            'full_name' => 'full_name_latin',
            'address' => 'address_latin',
        ];
        $nativeFields = [];

        foreach ($fieldMap as $field => $latinField) {
            $value = trim((string) data_get($parsed, $field));
            if ($this->containsSinhalaOrTamil($value)) {
                $nativeFields[$field] = $value;
                $parsed[$field.'_original'] = $value;
            }
        }

        if ($nativeFields === []) {
            return $parsed;
        }

        $translations = [];
        foreach ($nativeFields as $field => $nativeValue) {
            $translated = $this->translateWithGoogleFree($nativeValue);
            if (filled($translated) && ! $this->containsSinhalaOrTamil($translated)) {
                $translations[$field] = $translated;
            }
        }

        $accessToken = $this->getAccessToken();
        $apiKey = config('services.google_translate.api_key');

        if (count($translations) < count($nativeFields) && (filled($accessToken) || filled($apiKey))) {
            try {
                $http = $this->withGoogleCertificate(Http::acceptJson()->connectTimeout(5)->timeout(20));
                $url = 'https://translation.googleapis.com/language/translate/v2';
                if (filled($accessToken)) {
                    $http = $http->withToken($accessToken);
                } else {
                    $url .= '?key='.urlencode((string) $apiKey);
                }

                $response = $http->post($url, [
                    'q' => array_values($nativeFields),
                    'target' => 'en',
                    'format' => 'text',
                ]);

                if ($response->successful()) {
                    $translatedItems = data_get($response->json(), 'data.translations', []);
                    foreach (array_keys($nativeFields) as $index => $field) {
                        $translated = html_entity_decode(
                            trim((string) data_get($translatedItems, $index.'.translatedText')),
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        );
                        if (filled($translated) && ! $this->containsSinhalaOrTamil($translated)) {
                            $translations[$field] = $translated;
                        }
                    }
                }
            } catch (\Throwable $exception) {
                logger()->info('Google translation request failed: '.$exception->getMessage());
            }
        }

        foreach ($nativeFields as $field => $nativeValue) {
            $latinField = $fieldMap[$field];
            $fallback = trim((string) data_get($parsed, $latinField));
            $english = $translations[$field] ?? (! $this->containsSinhalaOrTamil($fallback) ? $fallback : '');

            // Try free online translation APIs only if no Latin text from the card
            if (blank($english)) {
                $english = $this->translateOrTransliterateFree($nativeValue);
            }

            // Keep native text as-is (user can edit in form) rather than garbage transliteration
            if (blank($english) || $english === $nativeValue) {
                $english = $nativeValue;
            }

            if ($field === 'full_name' && ! $this->containsSinhalaOrTamil($english)) {
                $english = collect(preg_split('/\s+/u', trim($english)) ?: [])
                    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)).mb_substr($part, 1))
                    ->implode(' ');
            }

            $parsed[$field] = $english;
            $parsed[$latinField] = $english;
        }

        return $parsed;
    }

    /**
     * Free translation and transliteration fallback using zero-cost public endpoints and local phonetic engine.
     */
    private function translateWithGoogleFree(string $nativeText): string
    {
        if (blank($nativeText)) {
            return '';
        }

        try {
            $sourceLanguage = preg_match('/[\x{0B80}-\x{0BFF}]/u', $nativeText) ? 'ta' : 'si';
            $http = $this->withTrustedCertificate(
                Http::acceptJson()->connectTimeout(5)->timeout(10)
            );
            $response = $http->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => $sourceLanguage,
                'tl' => 'en',
                'dt' => 't',
                'q' => $nativeText,
            ]);

            if (! $response->successful()) {
                return '';
            }

            $translated = collect($response->json('0', []))
                ->map(fn ($segment) => is_array($segment) ? ($segment[0] ?? '') : '')
                ->implode('');
            $translated = trim(html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return filled($translated) && ! $this->containsSinhalaOrTamil($translated)
                ? $translated
                : '';
        } catch (\Throwable $exception) {
            logger()->info('Google free translation request failed: '.$exception->getMessage());

            return '';
        }
    }

    private function translateOrTransliterateFree(string $nativeText): string
    {
        if (blank($nativeText)) {
            return '';
        }

        // 1. Free Google Translate public GTX endpoint (No API key needed)
        try {
            $response = $this->withTrustedCertificate(Http::acceptJson())
                ->connectTimeout(5)
                ->timeout(10)
                ->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => 'gtx',
                    'sl' => 'auto',
                    'tl' => 'en',
                    'dt' => 't',
                    'q' => $nativeText,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                    $translated = '';
                    foreach ($data[0] as $segment) {
                        if (isset($segment[0])) {
                            $translated .= $segment[0];
                        }
                    }
                    $translated = trim($translated);
                    if (filled($translated) && ! $this->containsSinhalaOrTamil($translated)) {
                        return $translated;
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->info('Free Google Translate gtx fallback skipped: '.$e->getMessage());
        }

        // 2. MyMemory Free Translation API (No API key needed)
        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $nativeText,
                    'langpair' => 'autodetect|en',
                ]);

            if ($response->successful()) {
                $translated = (string) data_get($response->json(), 'responseData.translatedText', '');
                $translated = trim(html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (filled($translated)
                    && ! $this->containsSinhalaOrTamil($translated)
                    && ! str_contains(strtolower($translated), 'is invalid')) {
                    return $translated;
                }
            }
        } catch (\Throwable $e) {
            logger()->info('MyMemory free translation fallback skipped: '.$e->getMessage());
        }

        // 3. Offline Sinhala & Tamil Phonetic Transliteration Engine (100% Offline / Free)
        return $this->transliterateSinhalaTamilToLatin($nativeText);
    }

    /**
     * Offline phonetic transliteration dictionary for Sinhala & Tamil script to Latin alphabet.
     */
    private function transliterateSinhalaTamilToLatin(string $text): string
    {
        $sinhalaMap = [
            'අ'=>'a', 'ආ'=>'aa', 'ඇ'=>'a', 'ඈ'=>'aa', 'ඉ'=>'i', 'ඊ'=>'ee', 'උ'=>'u', 'ඌ'=>'oo',
            'ඍ'=>'ru', 'එ'=>'e', 'ඒ'=>'e', 'ඓ'=>'ai', 'ඔ'=>'o', 'ඕ'=>'o', 'ඖ'=>'au',
            'ක'=>'ka', 'ඛ'=>'kha', 'ග'=>'ga', 'ඝ'=>'gha', 'ඞ'=>'nga', 'ඟ'=>'nga',
            'ච'=>'cha', 'ඡ'=>'chha', 'ජ'=>'ja', 'ඣ'=>'jha', 'ඤ'=>'nya', 'ඥ'=>'gna',
            'ට'=>'ta', 'ඨ'=>'tha', 'ඩ'=>'da', 'ඪ'=>'dha', 'ණ'=>'na', 'ඬ'=>'nda',
            'ත'=>'tha', 'ථ'=>'thha', 'ද'=>'da', 'ධ'=>'dha', 'න'=>'na', 'ඳ'=>'nda',
            'ප'=>'pa', 'ඵ'=>'pha', 'බ'=>'ba', 'භ'=>'bha', 'ම'=>'ma', 'ඹ'=>'mba',
            'ය'=>'ya', 'ර'=>'ra', 'ල'=>'la', 'ව'=>'va', 'ශ'=>'sha', 'ෂ'=>'sha', 'ස'=>'sa', 'හ'=>'ha', 'ළ'=>'la', 'ෆ'=>'fa',
            'ං'=>'n', 'ඃ'=>'h',
            '්'=>'', 'ා'=>'a', 'ැ'=>'a', 'ෑ'=>'aa', 'ි'=>'i', 'ී'=>'ee', 'ු'=>'u', 'ූ'=>'oo',
            'ෘ'=>'ru', 'ෙ'=>'e', 'ේ'=>'e', 'ෛ'=>'ai', 'ො'=>'o', 'ෝ'=>'o', 'ෞ'=>'au', 'ෟ'=>'u', 'ෲ'=>'roo', 'ෳ'=>'lu',
        ];

        $tamilMap = [
            'அ'=>'a', 'ஆ'=>'aa', 'இ'=>'i', 'ஈ'=>'ee', 'உ'=>'u', 'ஊ'=>'oo', 'எ'=>'e', 'ஏ'=>'ae', 'ஐ'=>'ai', 'ஒ'=>'o', 'ஓ'=>'oo', 'ஔ'=>'au',
            'க'=>'ka', 'ங'=>'nga', 'ச'=>'cha', 'ஞ'=>'nya', 'ட'=>'ta', 'ண'=>'na', 'த'=>'tha', 'ந'=>'na', 'ப'=>'pa', 'ம'=>'ma', 'ய'=>'ya', 'ர'=>'ra', 'ல'=>'la', 'வ'=>'va', 'ழ'=>'zha', 'ள'=>'la', 'ற'=>'ra', 'ன'=>'na', 'ஜ'=>'ja', 'ஷ'=>'sha', 'ஸ'=>'sa', 'ஹ'=>'ha',
            'ா'=>'aa', 'ி'=>'i', 'ீ'=>'ee', 'ு'=>'u', 'ூ'=>'oo', 'ெ'=>'e', 'ே'=>'ae', 'ை'=>'ai', 'ொ'=>'o', 'ோ'=>'oo', 'ௌ'=>'au', '்'=>'',
        ];

        $map = array_merge($sinhalaMap, $tamilMap);
        $result = strtr($text, $map);
        $result = preg_replace('/[^\x20-\x7E]/u', '', $result);
        $result = preg_replace('/\s+/', ' ', $result);
        return ucwords(strtolower(trim($result)));
    }

    private function extractDocumentNumber(string $fullText, $lines, string $docType): string
    {
        $patterns = match ($docType) {
            'nic' => ['/\b(19\d{10}|20\d{10}|\d{9}[VXvx])\b/u'],
            'driving_license' => ['/\b([A-Z]{1,2}\s?\d{7,8})\b/iu'],
            'passport' => ['/\b([A-Z]\s?\d{7})\b/iu'],
            default => [],
        };

        foreach (array_merge($patterns, [
            '/\b(19\d{10}|20\d{10}|\d{9}[VXvx])\b/u',
            '/\b([A-Z]{1,2}\s?\d{7,8})\b/iu',
        ]) as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                return strtoupper(preg_replace('/\s+/', '', $matches[1]));
            }
        }

        $labeled = $this->extractLabeledBlock($lines, [
            'passport no', 'passport number', 'licence no', 'license no',
            'driving licence no', 'driving license no', 'nic no', 'nic number',
            'identity card no', 'identity number', 'id no', 'document no',
            'ගමන් බලපත්‍ර අංකය', 'හැඳුනුම්පත් අංකය', 'රියදුරු බලපත්‍ර අංකය',
            'கடவுச்சீட்டு இல', 'அடையாள அட்டை இல', 'சாரதி அனுமதிப்பத்திர இல',
        ], 1, true);
        $candidate = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $labeled));
        if (preg_match('/^(?:\d{9}[VX]|(?:19|20)\d{10}|[A-Z]{1,2}\d{7,8})$/', $candidate)) {
            return $candidate;
        }

        return '';
    }

    private function extractName($lines, string $docType): string
    {
        // 1. Try ENGLISH-ONLY labeled extraction first (Sri Lankan docs have English labels)
        $englishLabeled = $this->extractLabeledBlock($lines, [
            'full name', 'name in full', 'name', 'holder name',
        ], 2);
        if (filled($englishLabeled)
            && ! in_array($docType, ['passport', 'driving_license'], true)
            && ! $this->containsSinhalaOrTamil($englishLabeled)) {
            return $englishLabeled;
        }

        // 2. Try all labels including native
        $fullName = $this->extractLabeledBlock($lines, [
            'full name', 'name in full', 'name', 'holder name',
            'සම්පූර්ණ නම', 'නම', 'முழுப் பெயர்', 'முழு பெயர்', 'பெயர்',
        ], 2);
        if (filled($fullName) && ! in_array($docType, ['passport', 'driving_license'], true)) {
            return $fullName;
        }

        if (in_array($docType, ['passport', 'driving_license'], true)) {
            // Try English surname+given first
            $surname = $this->extractLabeledBlock($lines, ['surname', 'family name'], 1);
            $givenNames = $this->extractLabeledBlock($lines, [
                'given names', 'given name', 'other names', 'forenames',
            ], 2);
            $combined = trim($givenNames.' '.$surname);
            if (filled($combined) && ! $this->containsSinhalaOrTamil($combined)) {
                return $combined;
            }

            // Try native surname+given
            if (blank($combined)) {
                $surname = $this->extractLabeledBlock($lines, [
                    'surname', 'family name', 'වාසගම', 'குடும்பப் பெயர்',
                ], 1);
                $givenNames = $this->extractLabeledBlock($lines, [
                    'given names', 'given name', 'other names', 'forenames',
                    'වෙනත් නම්', 'ලබා දුන් නම්', 'கொடுக்கப்பட்ட பெயர்கள்', 'ஏனைய பெயர்கள்',
                ], 2);
                $combined = trim($givenNames.' '.$surname);
                if (filled($combined)) {
                    return $combined;
                }
            }
        }

        // 3. Look for Latin name lines (mixed case accepted)
        $nameCandidates = [];
        $addressContext = $lines
            ->filter(fn ($line) => preg_match('/\d{1,4}\s*[\/-]\s*\d{1,4}/', $line))
            ->map(fn ($line) => strtoupper((string) preg_replace('/[^A-Z ]/', '', $line)))
            ->implode(' ');

        foreach ($lines as $line) {
            $candidate = trim((string) preg_replace('/[^A-Za-z .-]/', '', $line));
            $words = preg_split('/\s+/', $candidate, -1, PREG_SPLIT_NO_EMPTY);
            $upper = strtoupper($candidate);
            if (count($words) >= 2 && count($words) <= 7
                && min(array_map('strlen', $words)) >= 2
                && preg_match('/^[A-Za-z]+(?:[ .-]+[A-Za-z]+)+$/', $candidate)
                && ! str_contains($addressContext, str_replace(['.', '-'], '', $upper))
                && ! preg_match('/SRI LANKA|IDENTITY|NATIONAL|HOLDER|SIGNATURE|REPUBLIC|DEPARTMENT|DATE|PLACE|REGISTRATION/i', $candidate)) {
                $nameCandidates[] = $candidate;
            }
        }

        if ($nameCandidates) {
            usort($nameCandidates, fn ($a, $b) => strlen(preg_replace('/[^A-Za-z]/', '', $b)) <=> strlen(preg_replace('/[^A-Za-z]/', '', $a)));
            return $nameCandidates[0];
        }

        // 4. Last resort: Sinhala/Tamil name line
        foreach ($lines as $line) {
            if ($this->containsSinhalaOrTamil($line)
                && mb_strlen($line) > 3
                && ! $this->looksLikeFieldLabel($line)
                && ! preg_match('/\d/u', $line)
                && ! preg_match('/ලිපිනය|මාවත|පාර|முகவரி|வீதி|சாலை/u', $line)) {
                return $line;
            }
        }

        return '';
    }

    private function extractLatinName($lines, string $docType = 'nic'): string
    {
        // Driving licences and passports split the holder name into surname
        // and given-name fields; combine those before generic "name" matching.
        if (in_array($docType, ['passport', 'driving_license'], true)) {
            $surname = $this->extractLabeledBlock($lines, ['surname', 'family name'], 1);
            $givenNames = $this->extractLabeledBlock($lines, [
                'given names', 'given name', 'other names', 'forenames',
            ], 2);
            $combined = trim($givenNames.' '.$surname);
            if (filled($combined) && ! $this->containsSinhalaOrTamil($combined)) {
                return $combined;
            }
        }

        // 1. Try English labeled extraction first
        $labeled = $this->extractLabeledBlock($lines, [
            'full name', 'name in full', 'name', 'holder name',
        ], 2);
        if (filled($labeled) && ! $this->containsSinhalaOrTamil($labeled)) {
            return $labeled;
        }

        // 2. Scan for Latin name lines (mixed case accepted)
        $candidates = [];
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if (! $this->containsSinhalaOrTamil($candidate)
                && ! $this->looksLikeFieldLabel($candidate)
                && preg_match('/^[A-Za-z][A-Za-z .\'\-]{3,79}$/', $candidate)
                && count(preg_split('/\s+/', $candidate, -1, PREG_SPLIT_NO_EMPTY)) >= 2
                && ! preg_match('/SRI LANKA|IDENTITY|NATIONAL|PASSPORT|LICEN[CS]E|REPUBLIC|DEPARTMENT|DATE|ADDRESS|SIGNATURE/i', $candidate)) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $candidates[0] ?? '';
    }

    private function extractAddress($lines, string $docType): string
    {
        $labeledAddress = $this->extractLabeledBlock($lines, [
            'permanent address', 'residential address', 'place of residence',
            'permanent place of residence', 'address',
            'ස්ථිර ලිපිනය', 'ලිපිනය', 'நிரந்தர முகவரி', 'வசிப்பிட முகவரி', 'முகவரி',
        ], 4);
        if (filled($labeledAddress)) {
            return $labeledAddress;
        }

        foreach ($lines as $line) {
            $clean = trim((string) preg_replace('/[^A-Z0-9,\/ .-]/', '', strtoupper($line)));
            if (preg_match('/^\d{1,4}[A-Z]?(?:[\/-]\d{1,4}[A-Z]?)?,?\s+[A-Z]{3,}(?:\s+[A-Z]{3,})+$/', $clean)) {
                return $clean;
            }
        }

        $addressParts = [];
        foreach ($lines as $line) {
            if (preg_match('/(?:\b(?:road|street|mawatha|colombo|kandy|galle|jaffna|no)\b|අංක|පාර|මාවත|කොළඹ|இல|வீதி|சாலை|கொழும்பு|யாழ்ப்பாணம்)/iu', $line)) {
                $addressParts[] = $line;
            }
        }

        if (! $addressParts) {
            foreach ($lines as $line) {
                $clean = trim((string) preg_replace('/[^A-Z0-9,\/ .-]/', '', strtoupper($line)));
                if (preg_match('/^\d{1,4}[A-Z]?(?:[\/-]\d{1,4}[A-Z]?)?,?\s+[A-Z]{3,}(?:\s+[A-Z]{3,})+$/', $clean)) {
                    $addressParts[] = $clean;
                }
            }
        }

        return implode(', ', array_unique($addressParts));
    }

    private function extractLatinAddress($lines): string
    {
        // 1. Try English labeled extraction first
        $labeled = $this->extractLabeledBlock($lines, [
            'permanent address', 'residential address', 'place of residence',
            'permanent place of residence', 'address',
        ], 4);
        if (filled($labeled) && ! $this->containsSinhalaOrTamil($labeled)) {
            return $labeled;
        }

        // 2. Look for lines starting with house numbers (e.g. "12 Galle Road")
        foreach ($lines as $line) {
            $clean = trim((string) $line);
            if (! $this->containsSinhalaOrTamil($clean)
                && preg_match('/^\d{1,4}[A-Za-z]?(?:[\/-]\d{1,4}[A-Za-z]?)?,?\s+[A-Za-z]{2,}/i', $clean)
                && mb_strlen($clean) >= 8) {
                return $clean;
            }
        }

        // 3. Look for lines with common Sri Lankan address keywords
        $parts = [];
        foreach ($lines as $line) {
            if (! $this->containsSinhalaOrTamil($line)
                && preg_match('/\b(road|street|lane|avenue|place|drive|crescent|mawatha|veediya|vidiya|colombo|kandy|galle|jaffna|matara|kurunegala|negombo|ratnapura|anuradhapura|batticaloa|trincomalee|badulla|nuwara eliya|kalutara|kegalle|puttalam|ampara|hambantota|monaragala|polonnaruwa|mannar|vavuniya|kilinochchi|mullaitivu|no\.)\b/i', $line)) {
                $parts[] = $line;
            }
        }

        return implode(', ', $parts);
    }

    /** Extract a value printed beside a label or on the following OCR lines. */
    private function extractLabeledBlock($lines, array $labels, int $maxLines, bool $allowDigitsOnly = false): string
    {
        $labelPattern = implode('|', array_map(fn ($label) => preg_quote($label, '/'), $labels));
        $lineValues = $lines->values();

        foreach ($lineValues as $index => $originalLine) {
            $line = trim((string) preg_replace('/^\s*\d{1,2}[A-Z]?\s*[\.\):\-]\s*/iu', '', $originalLine));
            if (! preg_match('/^(?:'.$labelPattern.')(?:\s*[:\.\-]\s*|\s+)?(.*)$/iu', $line, $matches)) {
                continue;
            }

            $parts = [];
            $inlineValue = trim($matches[1] ?? '');
            if (filled($inlineValue)) {
                $parts[] = $inlineValue;
            }

            for ($offset = 1; count($parts) < $maxLines && $index + $offset < $lineValues->count(); $offset++) {
                $candidate = trim((string) $lineValues[$index + $offset]);
                if (blank($candidate) || $this->looksLikeFieldLabel($candidate)) {
                    break;
                }
                if (! $allowDigitsOnly && preg_match('/^(?:DOB|DATE|SEX|NATIONALITY|SIGNATURE|ISSUED|EXPIRES?|FRONT SIDE|BACK SIDE|DOCUMENT DETAILS)\b/iu', $candidate)) {
                    break;
                }
                if ($parts !== [] && $this->containsSinhalaOrTamil($parts[0]) !== $this->containsSinhalaOrTamil($candidate)) {
                    break;
                }
                $parts[] = $candidate;
            }

            return trim(implode(', ', array_unique($parts)));
        }

        return '';
    }

    private function looksLikeFieldLabel(string $line): bool
    {
        $clean = trim((string) preg_replace('/^\s*\d{1,2}[A-Z]?\s*[\.\):\-]\s*/iu', '', $line));

        if (preg_match('/^(?:FULL NAME|NAME IN FULL|NAME|HOLDER NAME|SURNAME|FAMILY NAME|GIVEN NAMES?|OTHER NAMES|FORENAMES|ADDRESS|PERMANENT ADDRESS|RESIDENTIAL ADDRESS|PLACE OF RESIDENCE|PERMANENT PLACE OF RESIDENCE|PASSPORT (?:NO|NUMBER)|LICEN[CS]E NO|NIC (?:NO|NUMBER)|IDENTITY (?:CARD NO|NUMBER)|සම්පූර්ණ නම|නම|වාසගම|ලිපිනය|ස්ථිර ලිපිනය|முழுப் பெயர்|முழு பெயர்|பெயர்|குடும்பப் பெயர்|முகவரி|நிரந்தர முகவரி)(?:\s*[:\.\-]\s*|\s+).+/iu', $clean)) {
            return true;
        }

        return preg_match(
            '/^(?:full name|name in full|name|holder name|surname|family name|given names?|other names|forenames|address|permanent address|residential address|place of residence|permanent place of residence|passport (?:no|number)|licen[cs]e no|nic (?:no|number)|identity (?:card no|number)|date of birth|dob|nationality|sex|signature|date of issue|date of expiry|සම්පූර්ණ නම|නම|වාසගම|ලිපිනය|ස්ථිර ලිපිනය|මுழுப் பெயர்|முழு பெயர்|பெயர்|குடும்பப் பெயர்|முகவரி|நிரந்தர முகவரி)(?:\s*[:\.\-]?\s*)$/iu',
            $clean
        ) === 1;
    }

    private function containsSinhalaOrTamil(string $value): bool
    {
        return preg_match('/[\x{0B80}-\x{0BFF}\x{0D80}-\x{0DFF}]/u', $value) === 1;
    }
}
