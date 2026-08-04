<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class TesseractOcrService
{
    private array $languageCache = [];

    private array $installedLanguageCache = [];

    /**
     * Preprocess a document, determine its text orientation, then OCR each
     * script independently so the language models cannot corrupt one another.
     */
    public function extractLanguageTexts(string $filePath): array
    {
        $empty = ['eng' => '', 'sin' => '', 'tam' => '', 'combined' => '', 'confidence' => 0.0];
        $executable = $this->findExecutable();
        if (! $executable || ! file_exists($filePath) || ! function_exists('imagecreatefromstring')) {
            return $empty;
        }

        @set_time_limit(120);
        $processedPath = $this->preprocessDocument($filePath);
        $source = @imagecreatefromstring((string) file_get_contents($processedPath));
        if (! $source) {
            $this->removeTemporaryPreprocessedFile($processedPath, $filePath);

            return $empty;
        }

        $temporaryDirectory = storage_path('app/ocr-temp');
        if (! is_dir($temporaryDirectory)) {
            @mkdir($temporaryDirectory, 0775, true);
        }

        $bestOrientation = null;
        $rotationResults = [];
        try {
            foreach ([0, 90, 180, 270] as $rotation) {
                $image = $rotation === 0 ? $source : imagerotate($source, $rotation, 255);
                if (! $image) {
                    continue;
                }
                $path = $temporaryDirectory.DIRECTORY_SEPARATOR.uniqid("orientation-{$rotation}-", true).'.png';
                imagepng($image, $path, 3);
                if ($image !== $source) {
                    imagedestroy($image);
                }

                $languageResults = [];
                foreach (['eng', 'sin', 'tam'] as $language) {
                    $languageResults[$language] = $this->runTesseractTsv(
                        $executable,
                        $path,
                        $language,
                        6
                    );
                }
                $result = [
                    'text' => collect($languageResults)->pluck('text')->filter()->implode("\n"),
                    'confidence' => round((float) collect($languageResults)->avg('confidence'), 2),
                    'score' => round((float) collect($languageResults)->sum('score'), 2),
                    'languages' => $languageResults,
                ];
                $rotationResults[$rotation] = $result;
                @unlink($path);

                if ($bestOrientation === null || $result['score'] > $rotationResults[$bestOrientation]['score']) {
                    $bestOrientation = $rotation;
                }
            }

            if ($bestOrientation === null) {
                return $empty;
            }

            $oriented = $bestOrientation === 0 ? $source : imagerotate($source, $bestOrientation, 255);
            if (! $oriented) {
                return $empty;
            }
            $orientedPath = $temporaryDirectory.DIRECTORY_SEPARATOR.uniqid('oriented-', true).'.png';
            imagepng($oriented, $orientedPath, 3);
            if ($oriented !== $source) {
                imagedestroy($oriented);
            }

            $output = [];
            foreach (['eng', 'sin', 'tam'] as $language) {
                $best = $rotationResults[$bestOrientation]['languages'][$language];
                foreach ([11] as $pageMode) {
                    $candidate = $this->runTesseractTsv($executable, $orientedPath, $language, $pageMode);
                    if ($candidate['score'] > $best['score']) {
                        $best = $candidate;
                    }
                }
                $output[$language] = $best['text'];
                $output[$language.'_confidence'] = $best['confidence'];
            }
            $output['combined'] = $rotationResults[$bestOrientation]['text'];
            $output['confidence'] = $rotationResults[$bestOrientation]['confidence'];
            $output['rotation'] = $bestOrientation;
            @unlink($orientedPath);

            return array_merge($empty, $output);
        } catch (\Throwable $exception) {
            logger()->warning('Structured Tesseract OCR failed: '.$exception->getMessage());

            return $empty;
        } finally {
            if (isset($orientedPath) && file_exists($orientedPath)) {
                @unlink($orientedPath);
            }
            imagedestroy($source);
            $this->removeTemporaryPreprocessedFile($processedPath, $filePath);
        }
    }

    /**
     * Run Tesseract OCR on a local image file.
     *
     * @param string $filePath
     * @return string
     */
    public function extractText(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }

        $executable = $this->findExecutable();

        if (! $executable || ! function_exists('imagecreatefromstring')) return '';

        @set_time_limit(120);

        $source = @imagecreatefromstring((string) file_get_contents($filePath));
        if (! $source) return '';
        $bestText = '';
        $bestScore = 0;
        $results = [];
        $bestRotation = 0;

        $languages = $this->availableLanguages($executable);
        $tessdataDir = $this->tessdataDirectory();
        $tessdataOption = $tessdataDir
            ? ' --tessdata-dir '.escapeshellarg($tessdataDir)
            : '';

        try {
            foreach ([0, 90, 180, 270] as $rotation) {
                $image = $rotation === 0 ? $source : imagerotate($source, $rotation, 255);
                if (! $image) continue;
                imagefilter($image, IMG_FILTER_GRAYSCALE);
                imagefilter($image, IMG_FILTER_CONTRAST, -25);
                $width = imagesx($image);
                $height = imagesy($image);
                if (max($width, $height) < 1800) {
                    $scale = min(3, 1800 / max($width, $height));
                    $scaled = imagescale($image, (int) ($width * $scale), (int) ($height * $scale), IMG_BICUBIC);
                    if ($scaled) {
                        if ($image !== $source) imagedestroy($image);
                        $image = $scaled;
                    }
                }

                $temporaryDirectory = storage_path('app/ocr-temp');
                if (! is_dir($temporaryDirectory)) @mkdir($temporaryDirectory, 0775, true);
                $temporary = $temporaryDirectory.DIRECTORY_SEPARATOR.uniqid('visitor-ocr-', true).'.png';
                imagepng($image, $temporary, 3);
                if ($image !== $source) imagedestroy($image);

                foreach ([6, 11] as $pageMode) {
                    $command = sprintf('%s %s stdout%s -l %s --oem 1 --psm %d 2>&1', escapeshellarg($executable), escapeshellarg($temporary), $tessdataOption, escapeshellarg($languages), $pageMode);
                    $text = trim((string) @shell_exec($command));
                    $score = $this->documentTextScore($text);
                    $results[] = ['rotation' => $rotation, 'text' => $text, 'score' => $score];
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestText = $text;
                        $bestRotation = $rotation;
                    }

                    if ($bestScore >= 16) {
                        break;
                    }
                }

                @unlink($temporary);

                if ($bestScore >= 16) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('Tesseract execution failed: '.$e->getMessage());
        } finally {
            if (isset($temporary) && file_exists($temporary)) {
                @unlink($temporary);
            }
            imagedestroy($source);
        }

        if ($bestScore < 8) return '';

        $usefulTexts = array_column(array_filter($results, fn ($result) => $result['rotation'] === $bestRotation && $result['score'] >= 8), 'text');
        array_unshift($usefulTexts, $bestText);
        return implode("\n", array_values(array_unique(array_filter($usefulTexts))));
    }

    /**
     * Run a fast English-only OCR pass on an image file.
     *
     * Multi-language Tesseract (eng+sin+tam) often garbles Latin characters
     * because Sinhala/Tamil models interfere with English recognition.
     * This method runs a separate pass with ONLY the 'eng' language
     * to get clean English text for names and addresses.
     *
     * @param string $filePath
     * @return string
     */
    public function extractTextEnglishOnly(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }

        $executable = $this->findExecutable();

        if (! $executable || ! function_exists('imagecreatefromstring')) return '';

        @set_time_limit(60);

        $source = @imagecreatefromstring((string) file_get_contents($filePath));
        if (! $source) return '';

        $tessdataDir = $this->tessdataDirectory();
        $tessdataOption = $tessdataDir
            ? ' --tessdata-dir '.escapeshellarg($tessdataDir)
            : '';

        $bestText = '';
        $bestScore = 0;

        try {
            // Only process upright orientation — English text on NICs is always horizontal
            $image = $source;
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            imagefilter($image, IMG_FILTER_CONTRAST, -30);

            // Sharpen the image for better text recognition
            $sharpen = [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1],
            ];
            imageconvolution($image, $sharpen, 8, 0);

            $width = imagesx($image);
            $height = imagesy($image);
            if (max($width, $height) < 2000) {
                $scale = min(3, 2000 / max($width, $height));
                $scaled = imagescale($image, (int) ($width * $scale), (int) ($height * $scale), IMG_BICUBIC);
                if ($scaled) {
                    $image = $scaled;
                }
            }

            $temporaryDirectory = storage_path('app/ocr-temp');
            if (! is_dir($temporaryDirectory)) @mkdir($temporaryDirectory, 0775, true);
            $temporary = $temporaryDirectory.DIRECTORY_SEPARATOR.uniqid('visitor-eng-', true).'.png';
            imagepng($image, $temporary, 3);
            if ($image !== $source) imagedestroy($image);

            // Run with ONLY English language — PSM 6 (uniform block) and PSM 4 (column of text)
            foreach ([6, 4, 3] as $pageMode) {
                $command = sprintf(
                    '%s %s stdout%s -l eng --oem 1 --psm %d 2>&1',
                    escapeshellarg($executable),
                    escapeshellarg($temporary),
                    $tessdataOption,
                    $pageMode
                );
                $text = trim((string) @shell_exec($command));
                $score = $this->documentTextScore($text);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestText = $text;
                }
                if ($bestScore >= 16) {
                    break;
                }
            }

            @unlink($temporary);
        } catch (\Throwable $e) {
            logger()->warning('Tesseract English-only extraction failed: '.$e->getMessage());
        } finally {
            if (isset($temporary) && file_exists($temporary)) {
                @unlink($temporary);
            }
            imagedestroy($source);
        }

        return $bestText;
    }

    private function preprocessDocument(string $filePath): string
    {
        if (filter_var(env('OCR_PREPROCESS_ENABLED', true), FILTER_VALIDATE_BOOL) === false) {
            return $filePath;
        }

        $script = base_path('app/Support/preprocess_document.py');
        if (! file_exists($script)) {
            return $filePath;
        }

        $temporaryDirectory = storage_path('app/ocr-temp');
        if (! is_dir($temporaryDirectory)) {
            @mkdir($temporaryDirectory, 0775, true);
        }
        $output = $temporaryDirectory.DIRECTORY_SEPARATOR.uniqid('preprocessed-', true).'.png';

        try {
            $process = new Process([
                (string) env('OCR_PYTHON_PATH', 'python'),
                $script,
                $filePath,
                $output,
            ]);
            $process->setTimeout((float) env('OCR_PREPROCESS_TIMEOUT', 30));
            $process->run();
            if ($process->isSuccessful() && file_exists($output) && filesize($output) > 0) {
                return $output;
            }

            logger()->info('OpenCV document preprocessing skipped: '.trim($process->getErrorOutput()));
        } catch (\Throwable $exception) {
            logger()->info('OpenCV document preprocessing unavailable: '.$exception->getMessage());
        }

        if (file_exists($output)) {
            @unlink($output);
        }

        return $filePath;
    }

    private function removeTemporaryPreprocessedFile(string $processedPath, string $originalPath): void
    {
        if ($processedPath !== $originalPath && file_exists($processedPath)) {
            @unlink($processedPath);
        }
    }

    private function runTesseractTsv(
        string $executable,
        string $imagePath,
        string $languages,
        int $pageMode
    ): array {
        $tessdataOption = $this->tessdataDirectory()
            ? ' --tessdata-dir '.escapeshellarg($this->tessdataDirectory())
            : '';
        $command = sprintf(
            '%s %s stdout%s -l %s --oem 1 --psm %d %s 2>&1',
            escapeshellarg($executable),
            escapeshellarg($imagePath),
            $tessdataOption,
            escapeshellarg($languages),
            $pageMode,
            escapeshellarg($this->tsvConfigPath($executable))
        );
        $tsv = (string) @shell_exec($command);
        $lines = [];
        $confidences = [];
        $wordCount = 0;

        foreach (preg_split('/\R/u', $tsv) ?: [] as $row) {
            $columns = explode("\t", $row, 12);
            if (count($columns) < 12 || $columns[0] !== '5') {
                continue;
            }
            $text = trim($columns[11]);
            $confidence = (float) $columns[10];
            if ($text === '' || $confidence < 0) {
                continue;
            }
            $lineKey = $columns[2].':'.$columns[3].':'.$columns[4];
            $lines[$lineKey][] = $text;
            $confidences[] = $confidence;
            $wordCount++;
        }

        $text = trim(implode("\n", array_map(fn ($words) => implode(' ', $words), $lines)));
        $confidence = $confidences === [] ? 0.0 : array_sum($confidences) / count($confidences);
        $characterCount = max(1, mb_strlen($text));
        $usefulCharacters = mb_strlen((string) preg_replace('/[^\p{L}\p{N}\s,\.\/\-]/u', '', $text));
        $cleanRatio = $usefulCharacters / $characterCount;
        preg_match_all('/\p{L}{5,}/u', $text, $longWords);
        $longWordBonus = min(45, count($longWords[0] ?? []) * 5);
        $coherentLineCount = collect(preg_split('/\R/u', $text) ?: [])
            ->filter(function ($line) {
                preg_match_all('/\p{L}{2,}/u', (string) $line, $words);

                return count($words[0] ?? []) >= 2;
            })->count();
        $score = $confidence
            + min(25, $wordCount * 0.6)
            + ($this->documentTextScore($text) * 1.5)
            + ($cleanRatio * 15)
            + $longWordBonus
            + min(20, $coherentLineCount * 4);

        return [
            'text' => $text,
            'confidence' => round($confidence, 2),
            'score' => round($score, 2),
        ];
    }

    private function tsvConfigPath(string $executable): string
    {
        if ($executable !== 'tesseract') {
            $candidate = dirname($executable).DIRECTORY_SEPARATOR.'tessdata'
                .DIRECTORY_SEPARATOR.'configs'.DIRECTORY_SEPARATOR.'tsv';
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return 'tsv';
    }

    private function documentTextScore(string $text): int
    {
        if ($text === '' || str_contains(strtolower($text), 'error opening data file')) return 0;
        $letters = preg_replace('/[^\p{L}]/u', '', $text) ?: '';
        $score = min(20, intdiv(mb_strlen($letters), 20));
        if (preg_match('/\b(?:19|20)\d{10}\b|\b\d{9}[VX]\b/i', $text)) $score += 30;
        foreach (['name', 'address', 'identity', 'national', 'date of birth', 'sri lanka', 'නම', 'ලිපිනය', 'பெயர்', 'முகவரி'] as $keyword) {
            if (stripos($text, $keyword) !== false) $score += 8;
        }
        return $score;
    }

    /** Use every configured OCR language that is installed on this server. */
    private function availableLanguages(string $executable): string
    {
        if (isset($this->languageCache[$executable])) {
            return $this->languageCache[$executable];
        }

        $requested = array_values(array_filter(explode('+', (string) env('TESSERACT_LANGUAGES', 'eng+sin+tam'))));
        $installed = $this->installedLanguages($executable);
        $available = array_values(array_filter($requested, fn ($language) => in_array(strtolower($language), $installed, true)));

        return $this->languageCache[$executable] = implode('+', $available ?: ['eng']);
    }

    public function supportsLanguages(array $languages): bool
    {
        $executable = $this->findExecutable();
        if (! $executable) {
            return false;
        }

        $installed = $this->installedLanguages($executable);

        return collect($languages)->every(
            fn ($language) => in_array(strtolower((string) $language), $installed, true)
        );
    }

    private function installedLanguages(string $executable): array
    {
        if (isset($this->installedLanguageCache[$executable])) {
            return $this->installedLanguageCache[$executable];
        }

        $tessdataOption = $this->tessdataDirectory()
            ? ' --tessdata-dir '.escapeshellarg($this->tessdataDirectory())
            : '';
        $output = (string) @shell_exec(escapeshellarg($executable).$tessdataOption.' --list-langs 2>&1');
        preg_match_all('/^[a-z0-9_-]+$/mi', $output, $matches);

        return $this->installedLanguageCache[$executable] = array_map('strtolower', $matches[0] ?? []);
    }

    private function tessdataDirectory(): ?string
    {
        $configured = env('TESSERACT_DATA_PATH', resource_path('tessdata'));
        if (filled($configured) && ! is_dir($configured)) {
            $configured = base_path($configured);
        }

        return filled($configured) && is_dir($configured) ? $configured : null;
    }

    /**
     * Find the Tesseract executable path prioritizing F drive and custom .env settings.
     *
     * @return string|null
     */
    public function findExecutable(): ?string
    {
        $envPath = env('TESSERACT_PATH');
        if (filled($envPath) && file_exists($envPath)) {
            return $envPath;
        }

        $candidates = [
            'F:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'F:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            'F:\\Tesseract-OCR\\tesseract.exe',
            'F:\\tesseract\\tesseract.exe',
            'F:\\Tesseract\\tesseract.exe',
            'F:\\tesseract.exe',
            'F:\\php-8.4\\tesseract.exe',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            getenv('LOCALAPPDATA').'\\Programs\\Tesseract-OCR\\tesseract.exe',
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
            '/opt/homebrew/bin/tesseract',
            'tesseract',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== 'tesseract' && file_exists($candidate)) {
                return $candidate;
            }
            if ($candidate === 'tesseract') {
                $test = @shell_exec('tesseract --version 2>&1');
                if ($test && preg_match('/tesseract\s+v?\d/i', $test) && ! str_contains(strtolower($test), 'not recognized')) {
                    return 'tesseract';
                }
            }
        }

        return null;
    }
}
