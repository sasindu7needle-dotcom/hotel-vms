<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Visitor;
use App\Models\VerifiedVisitor;
use App\Models\VisitorCategory;
use App\Models\ExhibitorProfile;
use App\Models\EventConfiguration;
use App\Models\EventRegistrationDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\VisitorMediaService;
use App\Services\VisitorRegistrationResumeService;
use App\Services\GeminiDocumentService;
use App\Services\EntranceCardImageService;
use F9WebLtd\QrCode\Facades\QrCode;

class VisitorController extends Controller
{
    /**
     * Begin a completely new registration without reusing the previous
     * visitor's document, captured photo, category, or payment state.
     */
    public function startNew(Request $request)
    {
        $request->session()->forget([
            'verification',
            'didit_verification',
            'visitor_registration',
            'visitor_category',
            'event_registration_day',
        ]);

        return Schema::hasTable('event_configurations') && EventConfiguration::query()
            ->where('singleton_key', EventConfiguration::SINGLETON_KEY)
            ->where('is_active', true)
            ->exists()
            ? redirect()->route('visitor.registration-days')
            : redirect()->route('visitor.create');
    }

    /** Display the independently payable registration form for each configured event date. */
    public function registrationDays(Request $request)
    {
        $eventConfiguration = Schema::hasTable('event_configurations')
            ? EventConfiguration::query()
            ->where('singleton_key', EventConfiguration::SINGLETON_KEY)
            ->where('is_active', true)
            ->first()
            : null;
        $registrationDays = $eventConfiguration
            ? $eventConfiguration->registrationDays()
                ->where('is_active', true)
                ->whereDate('event_date', '>=', today())
                ->get()
            : collect();
        $visitorCategory = $this->selfRegistrationCategory($request);

        return view('visitor.registration_days', compact('eventConfiguration', 'registrationDays', 'visitorCategory'));
    }

    /** Start a clean, separately paid registration for the selected event day. */
    public function selectRegistrationDay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'registration_day_id' => ['required', 'integer', 'exists:event_registration_days,id'],
        ]);
        $registrationDay = EventRegistrationDay::with('eventConfiguration')->findOrFail($validated['registration_day_id']);

        if (! $registrationDay->eventConfiguration?->is_active || ! $registrationDay->isOpenForRegistration()) {
            return back()->withErrors([
                'registration_day_id' => 'Registration for this event day is no longer available.',
            ]);
        }

        $visitorCategory = $this->selfRegistrationCategory($request);

        $request->session()->forget([
            'verification',
            'didit_verification',
            'visitor_registration',
        ]);
        $request->session()->put('event_registration_day', [
            'id' => $registrationDay->id,
            'label' => $registrationDay->label,
            'event_date' => $registrationDay->event_date->format('Y-m-d'),
            'entrance_fee' => $visitorCategory?->entrance_fee ?? $registrationDay->entrance_fee,
        ]);
        if ($visitorCategory) {
            $request->session()->put('visitor_category', [
                'id' => $visitorCategory->id,
                'name' => $visitorCategory->name,
                'entrance_fee' => $visitorCategory->entrance_fee,
            ]);
        }

        return redirect()->route('visitor.create');
    }

    /** Display the staff-operated registration form for walk-in visitors. */
    public function manualCreate(Request $request)
    {
        $categories = VisitorCategory::query()->where('is_active', true)->orderBy('name')->get();
        $exhibitorProfile = $this->exhibitorForManualRegistration($request);

        return view('visitor.manual_registration', compact('categories', 'exhibitorProfile'));
    }

    /**
     * Extract and securely retain the identity document used by the manual
     * registration form. The subsequent form submission uses this server-side
     * result, never a document number supplied by the browser.
     */
    public function manualVerifyIdentity(Request $request, GeminiDocumentService $gemini)
    {
        $validated = $request->validate([
            'document_type' => ['required', 'in:nic,driving_license,passport'],
            'document_front' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'document_back' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        if ($validated['document_type'] === 'nic' && ! $request->hasFile('document_back')) {
            return response()->json([
                'success' => false,
                'error' => 'Upload both the front and back of the NIC.',
            ], 422);
        }

        $front = $request->file('document_front');
        $back = $validated['document_type'] === 'nic' ? $request->file('document_back') : null;

        try {
            $identity = $gemini->extract(
                $front->getRealPath(),
                $front->getMimeType() ?: 'image/jpeg',
                $back?->getRealPath(),
                $back?->getMimeType(),
                false,
                $validated['document_type'],
            );
        } catch (\Throwable $exception) {
            Log::warning('Manual registration identity extraction failed.', [
                'document_type' => $validated['document_type'],
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'The identity document could not be read. Upload a clear, glare-free image and try again.',
            ], 422);
        }

        $documentNumber = $validated['document_type'] === 'driving_license'
            ? (string) data_get($identity, 'nic_number', data_get($identity, 'document_number'))
            : (string) data_get($identity, 'document_number');
        $documentNumber = $this->normaliseManualDocumentNumber($documentNumber, $validated['document_type']);

        if (! $this->isPlausibleManualDocumentNumber($documentNumber, $validated['document_type'])) {
            return response()->json([
                'success' => false,
                'error' => 'A valid identity number could not be read from this document. Upload a clearer image and try again.',
            ], 422);
        }
        if ($validated['document_type'] === 'nic' && $this->nicRegistrationExists($documentNumber)) {
            return response()->json([
                'success' => false,
                'error' => $this->duplicateNicMessage(),
            ], 409);
        }

        $verificationId = (string) Str::uuid();
        $documentFront = $this->storeManualImage($front, $verificationId.'-document-front');
        $documentBack = $back
            ? $this->storeManualImage($back, $verificationId.'-document-back')
            : null;

        $verification = [
            'verification_id' => $verificationId,
            'document_type' => $validated['document_type'],
            'document_number' => $documentNumber,
            'photo_path' => $documentFront['path'],
            'photo_mime' => $documentFront['mime'],
            'back_photo_path' => $documentBack['path'] ?? null,
            'back_photo_mime' => $documentBack['mime'] ?? null,
            'verified_at' => now()->toIso8601String(),
        ];
        $request->session()->put('manual_identity_verification', $verification);

        return response()->json([
            'success' => true,
            'verification_id' => $verificationId,
            'document_number' => $documentNumber,
        ]);
    }

    /** Store a manually registered visitor in the same directory used by Admin. */
    public function manualStore(Request $request)
    {
        $exhibitorProfile = $this->exhibitorForManualRegistration($request);
        if ($exhibitorProfile && ! $exhibitorProfile->hasMemberCapacity()) {
            return redirect()
                ->route('exhibitor.dashboard', $exhibitorProfile)
                ->withErrors(['members' => 'This exhibitor has reached its member limit.']);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email:rfc', 'max:100'],
            'document_type' => ['required', 'in:nic,driving_license,passport'],
            'identity_verification_id' => ['required', 'uuid'],
            'mobile_number' => ['required', 'regex:/^(?:\+94|94|0)?7\d{8}$/'],
            'whatsapp_number' => ['nullable', 'regex:/^(?:\+94|94|0)?7\d{8}$/'],
            'address' => ['required', 'string', 'max:500'],
            'occupation' => ['required', 'string', 'max:100'],
            'company' => ['required', 'string', 'max:150'],
            'category_id' => [
                $exhibitorProfile ? 'nullable' : 'required',
                Rule::exists('visitor_categories', 'id')->where('is_active', true),
            ],
            'entrance_fee' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'payment_slip' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
            'face_photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $identity = $request->session()->get('manual_identity_verification', []);
        if (! is_array($identity)
            || data_get($identity, 'verification_id') !== $validated['identity_verification_id']
            || data_get($identity, 'document_type') !== $validated['document_type']
            || blank(data_get($identity, 'document_number'))
            || blank(data_get($identity, 'photo_path'))) {
            return back()->withInput()->withErrors([
                'identity' => 'Verify the selected identity document before registering this visitor.',
            ]);
        }

        $verificationId = data_get($identity, 'verification_id');
        $documentNumber = $this->normaliseManualDocumentNumber(
            (string) data_get($identity, 'document_number'),
            $validated['document_type']
        );
        if ($validated['document_type'] === 'nic'
            && $this->nicRegistrationExists($documentNumber, (string) $verificationId)) {
            return back()->withInput()->withErrors([
                'identity' => $this->duplicateNicMessage(),
            ]);
        }
        $facePhoto = $this->storeManualImage($request->file('face_photo'), $verificationId.'-face');
        $paymentSlip = $request->hasFile('payment_slip')
            ? $this->storeManualPaymentSlip($request->file('payment_slip'), $verificationId.'-payment-slip')
            : null;
        $category = $exhibitorProfile
            ? null
            : VisitorCategory::query()->where('is_active', true)->findOrFail($validated['category_id']);

        try {
            $visitor = $this->persistVerifiedVisitor([
                'verification_id' => $verificationId,
                'document_type' => $validated['document_type'],
                'document_number' => $documentNumber,
                'full_name' => $validated['full_name'],
                'full_name_latin' => $validated['full_name'],
                'email' => strtolower(trim($validated['email'])),
                'address' => $validated['address'],
                'address_latin' => $validated['address'],
                'mobile_number' => $this->normaliseSriLankanPhone($validated['mobile_number']),
                'whatsapp_number' => $this->normaliseSriLankanPhone($validated['whatsapp_number'] ?: $validated['mobile_number']),
                'occupation' => $validated['occupation'],
                'company' => $exhibitorProfile?->company_name ?: $validated['company'],
                'category' => $exhibitorProfile ? 'Exhibitor' : $category->name,
                'visitor_category_id' => $exhibitorProfile ? null : $category?->id,
                'exhibitor_profile_id' => $exhibitorProfile?->id,
                'entrance_fee' => $exhibitorProfile ? 0 : $validated['entrance_fee'],
                'photo_path' => data_get($identity, 'photo_path'),
                'photo_mime' => data_get($identity, 'photo_mime'),
                'back_photo_path' => data_get($identity, 'back_photo_path'),
                'back_photo_mime' => data_get($identity, 'back_photo_mime'),
                'selfie_path' => $facePhoto['path'],
                'selfie_mime' => $facePhoto['mime'],
                'payment_slip_path' => $paymentSlip['path'] ?? null,
                'payment_slip_mime' => $paymentSlip['mime'] ?? null,
                'payment_slip_uploaded_at' => $paymentSlip ? now() : null,
                'identity_reviewed_at' => now(),
                'verified_at' => now(),
                'ocr_provider' => 'manual_registration',
            ], ['face_verification_status' => 'manual_review']);
        } catch (\Throwable $exception) {
            if ($validated['document_type'] === 'nic'
                && $this->nicRegistrationExists($documentNumber, (string) $verificationId)) {
                return back()->withInput()->withErrors([
                    'identity' => $this->duplicateNicMessage(),
                ]);
            }

            throw $exception;
        }

        $request->session()->put('visitor_registration', [
            'record_id' => $visitor->id,
            'verification_id' => $visitor->verification_id,
            'full_name' => $visitor->full_name,
            'email' => $visitor->email,
            'category' => $visitor->category,
            'photo_path' => $visitor->photo_path,
            'photo_mime' => $visitor->photo_mime,
            'selfie_path' => $visitor->selfie_path,
            'selfie_mime' => $visitor->selfie_mime,
            'payment_slip_path' => $visitor->payment_slip_path,
            'payment_slip_mime' => $visitor->payment_slip_mime,
            'manual_registration' => true,
            'exhibitor_profile_token' => $exhibitorProfile?->registration_token,
        ]);
        $request->session()->forget('manual_identity_verification');

        return redirect()->route('visitor.thank-you');
    }

    /**
     * Display the visitor registration form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function create(Request $request)
    {
        if ($redirect = $this->registrationDayRedirect($request)) {
            return $redirect;
        }

        $type = $request->query('type');
        $validTypes = ['nic', 'driving_license', 'passport'];
        if (!in_array($type, $validTypes)) {
            return view('visitor.select_type');
        }
        $verification = $request->session()->get('verification', $request->session()->get('didit_verification', []));

        if (! is_array($verification) || blank(data_get($verification, 'session_id'))) {
            return redirect()->route('visitor.create')->withErrors([
                'verification' => 'Please complete identity verification before registration.',
            ]);
        }

        if (! $this->hasCompleteIdentityFields($verification)) {
            return redirect()->route('visitor.upload_document', ['type' => data_get($verification, 'document_type', $type)])
                ->withErrors(['verification' => 'OCR did not read all required identity fields. Please upload clearer document photos and verify again.']);
        }

        if (blank(data_get($verification, 'selfie_path'))) {
            return redirect()->route('visitor.photo_capture');
        }

        $type = data_get($verification, 'document_type', $type);
        $category = $request->session()->get('visitor_category', []);
        $visitorCategory = $this->selfRegistrationCategory($request);
        if ($visitorCategory) {
            $category = [
                'id' => $visitorCategory->id,
                'name' => $visitorCategory->name,
                'entrance_fee' => $visitorCategory->entrance_fee,
            ];
            $request->session()->put('visitor_category', $category);
        }

        return view('visitor.create', compact('type', 'verification', 'category'));
    }

    /**
     * Display the document selection & upload/capture screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function showUploadDocument(Request $request)
    {
        if ($redirect = $this->registrationDayRedirect($request)) {
            return $redirect;
        }

        $type = $request->query('type', 'nic');
        $validTypes = ['nic', 'driving_license', 'passport'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'nic';
        }

        return view('visitor.upload_document', compact('type'));
    }

    public function showPhotoCapture(Request $request)
    {
        if ($redirect = $this->registrationDayRedirect($request)) {
            return $redirect;
        }

        $verification = $request->session()->get('verification', []);

        if (! is_array($verification) || blank(data_get($verification, 'session_id'))) {
            return redirect()->route('visitor.upload_document')->withErrors([
                'verification' => 'Upload and verify your identity document first.',
            ]);
        }

        if (! $this->hasCompleteIdentityFields($verification)) {
            return redirect()->route('visitor.upload_document', ['type' => data_get($verification, 'document_type', 'nic')])
                ->withErrors(['verification' => 'OCR did not read all required identity fields. Please upload clearer document photos and verify again.']);
        }

        if (filled(data_get($verification, 'selfie_path'))) {
            return redirect()->route('visitor.create', ['type' => data_get($verification, 'document_type', 'nic')]);
        }

        return view('visitor.live_face', ['type' => data_get($verification, 'document_type', 'nic')]);
    }

    /**
     * Validate the registration details and display the confirmation step.
     */
    public function confirm(Request $request, VisitorRegistrationResumeService $registrationResume)
    {
        if ($redirect = $this->registrationDayRedirect($request)) {
            return $redirect;
        }

        $verification = $request->session()->get('verification', $request->session()->get('didit_verification', []));
        $category = $request->session()->get('visitor_category', []);
        $visitorCategory = $this->selfRegistrationCategory($request);
        if ($visitorCategory) {
            $category = [
                'id' => $visitorCategory->id,
                'name' => $visitorCategory->name,
                'entrance_fee' => $visitorCategory->entrance_fee,
            ];
            $request->session()->put('visitor_category', $category);
        }
        $registrationDaySession = $request->session()->get('event_registration_day', []);
        $registrationDay = filled(data_get($registrationDaySession, 'id'))
            ? EventRegistrationDay::find(data_get($registrationDaySession, 'id'))
            : null;

        if (! is_array($verification) || blank(data_get($verification, 'session_id')) || blank(data_get($verification, 'selfie_path'))) {
            return redirect()->route('visitor.create')->withErrors([
                'verification' => 'Capture a visitor photo before registration.',
            ]);
        }

        $validated = $request->validate([
            'document_type' => 'required|in:nic,driving_license,passport',
            'full_name' => 'required|string|max:180',
            'document_number' => 'required|string|max:30',
            'email' => ['required', 'email:rfc', 'max:100'],
            'address' => 'required|string|max:500',
            'mobile_country_code' => ['nullable', 'regex:/^\+?[1-9]\d{0,2}$/'],
            'mobile_number' => ['required', 'regex:/^\d{4,14}$/'],
            'same_as_mobile' => 'nullable|boolean',
            'whatsapp_country_code' => ['nullable', 'regex:/^\+?[1-9]\d{0,2}$/'],
            'whatsapp_number' => ['required_unless:same_as_mobile,1', 'nullable', 'regex:/^\d{4,14}$/'],
            'occupation' => 'required|string|max:100',
            'company' => 'required|string|max:150',
        ], [
            'mobile_country_code.regex' => 'Enter a valid country code, for example +94.',
            'mobile_number.regex' => 'Enter the mobile number using digits only.',
            'whatsapp_country_code.regex' => 'Enter a valid country code, for example +94.',
            'whatsapp_number.regex' => 'Enter the WhatsApp number using digits only.',
        ]);

        $verifiedDocumentType = (string) data_get($verification, 'document_type', $validated['document_type']);
        $verifiedDocumentNumber = $verifiedDocumentType === 'nic'
            ? $this->normaliseManualDocumentNumber((string) data_get($verification, 'document_number'), 'nic')
            : strtoupper((string) preg_replace(
                '/\s+/',
                '',
                (string) data_get($verification, 'document_number')
            ));
        if ($verifiedDocumentNumber === '' || $verifiedDocumentType !== $validated['document_type']) {
            return redirect()->route('visitor.upload_document', ['type' => $verifiedDocumentType ?: 'nic'])
                ->withErrors(['verification' => 'Your verified identity no longer matches this registration. Upload the document again.']);
        }

        $mobileNumber = $this->normaliseInternationalPhone(
            (string) ($validated['mobile_country_code'] ?? '+94'),
            $validated['mobile_number']
        );
        $whatsappNumber = $request->boolean('same_as_mobile')
            ? $mobileNumber
            : $this->normaliseInternationalPhone(
                (string) ($validated['whatsapp_country_code'] ?? $validated['mobile_country_code'] ?? '+94'),
                (string) $validated['whatsapp_number']
            );
        $phoneErrors = [];
        if (! $this->isValidInternationalPhone($mobileNumber)) {
            $phoneErrors['mobile_number'] = 'Enter a valid international mobile number (maximum 15 digits including the country code).';
        }
        if (! $this->isValidInternationalPhone($whatsappNumber)) {
            $phoneErrors['whatsapp_number'] = 'Enter a valid international WhatsApp number (maximum 15 digits including the country code).';
        }
        if ($phoneErrors !== []) {
            return back()->withInput()->withErrors($phoneErrors);
        }

        if ($verifiedDocumentType === 'nic' && ! $request->boolean('name_confirmation')) {
            return back()->withInput()->withErrors([
                'name_confirmation' => 'Confirm that the English spelling of your name is correct.',
            ]);
        }

        $verificationId = (string) data_get($verification, 'verification_id', data_get($verification, 'session_id'));
        if ($verifiedDocumentType === 'nic') {
            $existingVisitor = $registrationResume->findByNic($verifiedDocumentNumber, $verificationId);
            if ($existingVisitor?->payment_status === 'paid') {
                return redirect()->to($registrationResume->resumePaid($request, $existingVisitor));
            }
            if ($existingVisitor) {
                return redirect()->to($registrationResume->resumePayment($request, $existingVisitor));
            }
        }

        $details = array_merge($validated, [
            'verification_id' => $verificationId,
            'didit_session_id' => $verificationId,
            'document_type' => $verifiedDocumentType,
            'full_name' => $validated['full_name'],
            'full_name_latin' => $validated['full_name'],
            'sinhala_name' => data_get($verification, 'sinhala_name'),
            'tamil_name' => data_get($verification, 'tamil_name'),
            'printed_english_name' => data_get($verification, 'printed_english_name'),
            'suggested_english_name' => data_get($verification, 'suggested_english_name'),
            'sinhala_transliteration' => data_get($verification, 'sinhala_transliteration'),
            'tamil_transliteration' => data_get($verification, 'tamil_transliteration'),
            'english_name_alternatives' => data_get($verification, 'english_name_alternatives', []),
            'name_review_status' => $verifiedDocumentType === 'nic'
                ? ($this->sameIdentityName($validated['full_name'], (string) data_get($verification, 'suggested_english_name')) ? 'confirmed' : 'corrected')
                : 'not_required',
            // Identity numbers always come from the server-side OCR session.
            // A readonly browser field is only a presentation safeguard.
            'document_number' => $verifiedDocumentNumber,
            'email' => strtolower(trim($validated['email'])),
            'address' => $validated['address'],
            'address_latin' => $validated['address'],
            'mobile_number' => $mobileNumber,
            'photo_url' => data_get($verification, 'photo_url'),
            'photo_path' => data_get($verification, 'photo_path'),
            'photo_mime' => data_get($verification, 'photo_mime'),
            'back_photo_path' => data_get($verification, 'back_photo_path'),
            'back_photo_mime' => data_get($verification, 'back_photo_mime'),
            'selfie_path' => data_get($verification, 'selfie_path'),
            'selfie_mime' => data_get($verification, 'selfie_mime'),
            'ocr_provider' => data_get($verification, 'provider'),
            'identity_reviewed_at' => now()->toIso8601String(),
            'verified_at' => data_get($verification, 'verified_at'),
            'whatsapp_number' => $whatsappNumber,
            'category' => data_get($category, 'name', 'Participant'),
            'visitor_category_id' => $visitorCategory?->id ?: data_get($category, 'id'),
            'entrance_fee' => $visitorCategory?->entrance_fee
                ?? data_get($category, 'entrance_fee')
                ?? $registrationDay?->entrance_fee,
            'event_registration_day_id' => $registrationDay?->id,
            'registration_day_label' => $registrationDay?->label,
            'registration_date' => $registrationDay?->event_date?->format('Y-m-d'),
        ]);

        $existingRegistration = filled(data_get($details, 'event_registration_day_id'))
            ? VerifiedVisitor::query()
                ->where('event_registration_day_id', data_get($details, 'event_registration_day_id'))
                ->where('document_number', $details['document_number'])
                ->where('verification_id', '!=', $details['verification_id'])
                ->first()
            : null;

        $paymentOverrides = [];
        if ($existingRegistration) {
            // The visitor has just completed identity and photo verification again,
            // so resume their existing day-specific registration instead of sending
            // them back to this form with an invisible duplicate-record error.
            $details = array_merge($details, [
                'verification_id' => $existingRegistration->verification_id,
                'didit_session_id' => $existingRegistration->verification_id,
                'record_id' => $existingRegistration->id,
                'category' => $existingRegistration->category ?: $details['category'],
                'entrance_fee' => $existingRegistration->entrance_fee ?? $details['entrance_fee'],
                'payment_method' => $existingRegistration->payment_method,
                'payment_status' => $existingRegistration->payment_status,
            ]);

            if ($existingRegistration->payment_status === 'paid') {
                $request->session()->put('visitor_registration', $details);

                return redirect()->route('visitor.thank-you');
            }

            // A new verification starts a new payment choice. Do not silently
            // reuse a stale Cash or card selection from an abandoned attempt.
            $details['payment_method'] = null;
            $details['payment_status'] = 'pending';
            $paymentOverrides = [
                'payment_method' => null,
                'payment_status' => 'pending',
            ];
        }

        $request->session()->put('visitor_registration', $details);
        try {
            $visitor = $this->persistVerifiedVisitor($details, $paymentOverrides);
        } catch (\Throwable $exception) {
            if ($verifiedDocumentType === 'nic') {
                $existingVisitor = $registrationResume->findByNic($verifiedDocumentNumber, $verificationId);
                if ($existingVisitor && $existingVisitor->payment_status !== 'paid') {
                    return redirect()->to($registrationResume->resumePayment($request, $existingVisitor));
                }
                if ($existingVisitor) {
                    return redirect()->to($registrationResume->resumePaid($request, $existingVisitor));
                }
            }

            Log::error('Verified visitor could not be saved.', [
                'verification_id' => data_get($details, 'verification_id'),
                'document_type' => data_get($details, 'document_type'),
                'document_number' => $this->maskDocumentNumber((string) data_get($details, 'document_number')),
                'exception_class' => $exception::class,
            ]);

            return back()->withInput()->withErrors([
                'verification' => 'Your verified details could not be saved. Please try again or contact reception.',
            ]);
        }
        $request->session()->put('visitor_registration.record_id', $visitor->id);

        return redirect()->route('visitor.confirm.show');
    }

    /**
     * Display the review and payment-method step from persisted session data.
     *
     * Keeping this page on a GET route makes it safe for mobile browsers to
     * restore or refresh after the registration form's POST request.
     */
    public function showConfirmation(Request $request)
    {
        $details = $request->session()->get('visitor_registration');

        if (! is_array($details) || blank(data_get($details, 'record_id'))) {
            return redirect()->route('visitor.create')->withErrors([
                'registration' => 'Your registration session has expired. Please register again.',
            ]);
        }

        return view('visitor.confirm', compact('details'));
    }

    /**
     * Serve temporary session photos for visitor confirmation view.
     */
    public function sessionPhoto(Request $request, string $type = 'selfie')
    {
        $verification = $request->session()->get('verification', []);
        $registration = $request->session()->get('visitor_registration', []);

        $mediaType = in_array($type, ['selfie', 'photo', 'back_photo'], true) ? $type : 'selfie';
        $pathKey = $mediaType.'_path';
        $mimeKey = $mediaType.'_mime';
        $visitor = filled(data_get($registration, 'record_id'))
            ? VerifiedVisitor::find(data_get($registration, 'record_id'))
            : null;

        // Once registration is persisted, the visitor record is canonical.
        // Never substitute an identity-document image for a missing selfie.
        if ($visitor) {
            $path = $visitor->{$pathKey};
            $mime = $visitor->{$mimeKey} ?: 'image/jpeg';
        } else {
            $path = data_get($registration, $pathKey, data_get($verification, $pathKey));
            $mime = data_get($registration, $mimeKey, data_get($verification, $mimeKey, 'image/jpeg'));
        }

        $media = app(VisitorMediaService::class);
        if (blank($path) || ! $media->exists($path)) {
            abort(404);
        }

        return $media->response($path, $mime, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Store the selected payment method and route to the appropriate payment step.
     */
    public function selectPaymentMethod(Request $request)
    {
        if (! $request->session()->has('visitor_registration')) {
            return redirect()->route('visitor.create');
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:visa_master',
        ]);

        $request->session()->put('visitor_registration.payment_method', $validated['payment_method']);

        $details = $request->session()->get('visitor_registration');
        // The method is saved separately, so the status remains a clear
        // state instead of repeating it (for example, Cash + Pending).
        $paymentStatus = 'pending';
        try {
            $visitor = $this->persistVerifiedVisitor($details, [
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Verified visitor payment state could not be saved.', [
                'verification_id' => data_get($details, 'verification_id'),
                'document_type' => data_get($details, 'document_type'),
                'document_number' => $this->maskDocumentNumber((string) data_get($details, 'document_number')),
                'exception_class' => $exception::class,
            ]);

            return back()->withErrors([
                'verification' => 'Your registration could not be updated. Please try again or contact reception.',
            ]);
        }
        $request->session()->put('visitor_registration.record_id', $visitor->id);

        return redirect()->route('visitor.payment.card');
    }

    /** Display the card gateway hand-off screen. */
    public function cardGateway(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details) || ! in_array(data_get($details, 'payment_method'), ['visa_master', 'amex'], true)) {
            return redirect()->route('visitor.create');
        }

        return view('visitor.payment.card', compact('details'));
    }

    /** Display the cash payment confirmation screen. */
    public function cashConfirmation(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details) || data_get($details, 'payment_method') !== 'cash') {
            return redirect()->route('visitor.create');
        }

        // Cash payments are completed by reception. Once the admin marks this
        // record paid, send the visitor straight to the badge on the next poll.
        $visitor = VerifiedVisitor::find(data_get($details, 'record_id'));
        if ($visitor && $visitor->payment_status === 'paid') {
            $paymentReference = data_get($details, 'payment_reference')
                ?: 'VMS-'.now()->format('Ymd').'-'.str_pad((string) $visitor->id, 6, '0', STR_PAD_LEFT);

            $request->session()->put('visitor_registration.payment_reference', $paymentReference);
            $request->session()->put('visitor_registration.payment_status', 'paid');

            return redirect()->route('visitor.thank-you');
        }

        return view('visitor.payment.cash', compact('details'));
    }

    /** Download the active registration's entrance card without exposing another visitor's record. */
    public function downloadCard(Request $request, EntranceCardImageService $cardImage)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details) || blank(data_get($details, 'record_id'))) {
            return redirect()->route('visitor.create');
        }

        $visitor = VerifiedVisitor::with(['eventRegistrationDay.eventConfiguration', 'exhibitorProfile'])
            ->find(data_get($details, 'record_id'));
        if (! $visitor) {
            return redirect()->route('visitor.create');
        }

        $eventName = $visitor->eventRegistrationDay?->eventConfiguration?->event_name
            ?: config('vms.event_name');
        $qrPayload = (string) ($visitor->verification_id ?: $visitor->id);
        $photoDataUri = filled($visitor->selfie_path)
            ? app(VisitorMediaService::class)->dataUri($visitor->selfie_path, $visitor->selfie_mime)
            : null;
        $cardStatus = $visitor->payment_status === 'paid' ? 'VERIFIED' : 'PAYMENT PENDING';
        $png = $cardImage->render($visitor, $eventName, $qrPayload, $cardStatus, $photoDataUri);
        $safeName = Str::slug($visitor->full_name ?: 'visitor') ?: 'visitor';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$safeName.'-entrance-card.png"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Record a successful payment hand-off and continue to the printable badge.
     */
    public function confirmPayment(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details) || blank(data_get($details, 'payment_method'))) {
            return redirect()->route('visitor.create');
        }

        if (data_get($details, 'payment_method') === 'cash') {
            return redirect()->route('visitor.payment.cash');
        }

        $visitor = VerifiedVisitor::find(data_get($details, 'record_id'));
        if (! $visitor) {
            return redirect()->route('visitor.create')->withErrors([
                'registration' => 'Your registration session has expired. Please register again.',
            ]);
        }

        $paymentReference = data_get($details, 'payment_reference')
            ?: 'VMS-'.now()->format('Ymd').'-'.str_pad((string) $visitor->id, 6, '0', STR_PAD_LEFT);

        $visitor->update([
            'payment_status' => 'paid',
            'paid_at' => $visitor->paid_at ?: now(),
            'registration_status' => 'registered',
        ]);

        $request->session()->put('visitor_registration.payment_reference', $paymentReference);
        $request->session()->put('visitor_registration.payment_status', 'paid');

        return redirect()->route('visitor.thank-you');
    }

    /** Display the final visitor badge after payment confirmation. */
    public function thankYou(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        $isManualRegistration = data_get($details, 'manual_registration') === true;

        if (! is_array($details) || (! $isManualRegistration && data_get($details, 'payment_status') !== 'paid')) {
            return redirect()->route('visitor.create');
        }

        $visitor = VerifiedVisitor::find(data_get($details, 'record_id'));
        if (! $visitor || (! $isManualRegistration && $visitor->payment_status !== 'paid')) {
            return redirect()->route('visitor.create');
        }

        $eventName = $visitor->eventRegistrationDay?->eventConfiguration?->event_name
            ?: config('vms.event_name');
        $qrPayload = (string) ($visitor->verification_id ?: $visitor->id);
        $qrCode = QrCode::format('svg')
            ->size(220)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($qrPayload);
        $profilePhotoAvailable = filled($visitor->selfie_path)
            && app(VisitorMediaService::class)->exists($visitor->selfie_path);

        return view('visitor.thank_you', compact(
            'details',
            'visitor',
            'eventName',
            'qrCode',
            'qrPayload',
            'profilePhotoAvailable'
        ));
    }

    /**
     * Only an authenticated exhibitor portal can start a member registration.
     * Ordinary manual registrations deliberately continue to work unchanged.
     */
    private function exhibitorForManualRegistration(Request $request): ?ExhibitorProfile
    {
        if (! $request->filled('exhibitor')) {
            return null;
        }

        $exhibitor = ExhibitorProfile::where('registration_token', $request->input('exhibitor'))->firstOrFail();
        abort_unless(
            (int) $request->session()->get('exhibitor_profile_id') === $exhibitor->id
                && $exhibitor->registered_at,
            403
        );

        return $exhibitor;
    }

    /**
     * Display the visitors list.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function list()
    {
        $visitors = Visitor::all();
        return view('visitor.list', compact('visitors'));
    }

    /**
     * Store a newly created visitor in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'purpose' => 'required',
        ]);

        $docType = $request->input('document_type');
        $docNum = $request->input('document_number');
        $purpose = $request->purpose;
        if ($docType && $docNum) {
            $typeLabel = strtoupper(str_replace('_', ' ', $docType));
            $purpose = "[{$typeLabel}: {$docNum}] " . $purpose;
        }

        Visitor::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'vehicle' => $request->vehicle ?? null,
            'purpose' => $purpose,
            'checkin_status' => true,
        ]);

        return redirect()->route('visitor.create', ['type' => $docType])->with('success', 'Visitor registered successfully!');
    }

    /**
     * Update the check-in status of a visitor to false (checkout).
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function checkout(Request $request, $id)
    {
        $visitor = Visitor::findOrFail($id);

        $visitor->checkin_status = false;
        $visitor->save();

        return redirect()->route('visitor.list')->with('success', 'Visitor check out successfully!');
    }

    private function persistVerifiedVisitor(array $details, array $overrides = []): VerifiedVisitor
    {
        $verificationId = data_get($details, 'verification_id', data_get($details, 'didit_session_id', data_get($details, 'session_id')))
            ?: (string) Str::uuid();
        $values = array_merge([
            'document_type' => data_get($details, 'document_type'),
            'document_number' => data_get($details, 'document_number'),
            'full_name' => data_get($details, 'full_name'),
            'full_name_latin' => data_get($details, 'full_name_latin'),
            'email' => data_get($details, 'email'),
            'address' => data_get($details, 'address'),
            'address_latin' => data_get($details, 'address_latin'),
            'mobile_number' => $this->normalisePhoneForStorage(data_get($details, 'mobile_number')),
            'whatsapp_number' => $this->normalisePhoneForStorage(data_get($details, 'whatsapp_number')),
            'occupation' => data_get($details, 'occupation'),
            'company' => data_get($details, 'company'),
            'photo_url' => data_get($details, 'photo_url'),
            'photo_path' => data_get($details, 'photo_path'),
            'photo_mime' => data_get($details, 'photo_mime'),
            'back_photo_path' => data_get($details, 'back_photo_path'),
            'back_photo_mime' => data_get($details, 'back_photo_mime'),
            'selfie_path' => data_get($details, 'selfie_path'),
            'selfie_mime' => data_get($details, 'selfie_mime'),
            'payment_slip_path' => data_get($details, 'payment_slip_path'),
            'payment_slip_mime' => data_get($details, 'payment_slip_mime'),
            'payment_slip_uploaded_at' => data_get($details, 'payment_slip_uploaded_at'),
            'ocr_provider' => data_get($details, 'ocr_provider'),
            'identity_reviewed_at' => data_get($details, 'identity_reviewed_at', now()),
            'category' => data_get($details, 'category'),
            'visitor_category_id' => data_get($details, 'visitor_category_id'),
            'event_registration_day_id' => data_get($details, 'event_registration_day_id'),
            'exhibitor_profile_id' => data_get($details, 'exhibitor_profile_id'),
            'entrance_fee' => data_get($details, 'entrance_fee'),
            'registration_status' => 'payment_pending',
            'verified_at' => data_get($details, 'verified_at', now()),
        ], $overrides);

        if (Schema::hasColumn('verified_visitors', 'didit_session_id')) {
            $values['didit_session_id'] = $verificationId;
        }
        if (Schema::hasColumn('verified_visitors', 'nic_registration_key')) {
            $values['nic_registration_key'] = data_get($details, 'document_type') === 'nic'
                ? $this->normaliseManualDocumentNumber((string) data_get($details, 'document_number'), 'nic')
                : null;
        }

        return VerifiedVisitor::updateOrCreate(
            ['verification_id' => $verificationId],
            $values
        );
    }

    private function storeManualImage($file, string $filename): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = app(VisitorMediaService::class)->storeAs($file, 'verified-visitors', $filename.'.'.$extension);

        return ['path' => $path, 'mime' => $file->getMimeType() ?: 'image/jpeg'];
    }

    private function storeManualPaymentSlip($file, string $filename): array
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $path = app(VisitorMediaService::class)->storeAs(
            $file,
            'verified-visitors',
            $filename.'.'.$extension
        );

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('The payment slip could not be stored.');
        }

        return ['path' => $path, 'mime' => $mime];
    }

    private function normaliseSriLankanPhone(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);
        if (str_starts_with($digits, '0')) {
            $digits = '94'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '94')) {
            $digits = '94'.$digits;
        }

        return substr($digits, 2);
    }

    private function normaliseInternationalPhone(string $countryCode, string $nationalNumber): string
    {
        $countryDigits = (string) preg_replace('/\D+/', '', $countryCode);
        $nationalDigits = (string) preg_replace('/\D+/', '', $nationalNumber);
        $nationalDigits = ltrim($nationalDigits, '0');

        return '+'.$countryDigits.$nationalDigits;
    }

    private function normalisePhoneForStorage(mixed $number): ?string
    {
        if (blank($number)) {
            return null;
        }

        $value = trim((string) $number);
        $digits = (string) preg_replace('/\D+/', '', $value);
        if (str_starts_with($value, '+')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '94') && strlen($digits) >= 11) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+94'.substr($digits, 1);
        }

        // Legacy registration sessions stored only the nine Sri Lankan digits.
        return '+94'.$digits;
    }

    private function isValidInternationalPhone(string $number): bool
    {
        return preg_match('/^\+[1-9]\d{7,14}$/', $number) === 1;
    }

    private function normaliseManualDocumentNumber(string $number, string $documentType): string
    {
        $number = strtoupper(trim($number));

        return in_array($documentType, ['nic', 'driving_license'], true)
            ? (string) preg_replace('/[^0-9VX]/', '', $number)
            : (string) preg_replace('/[^A-Z0-9]/', '', $number);
    }

    private function isPlausibleManualDocumentNumber(string $number, string $documentType): bool
    {
        if (in_array($documentType, ['nic', 'driving_license'], true)) {
            if (preg_match('/^\d{9}[VX]$/', $number) === 1) {
                $day = (int) substr($number, 2, 3);

                return ($day >= 1 && $day <= 366) || ($day >= 501 && $day <= 866);
            }

            if (preg_match('/^\d{12}$/', $number) !== 1) {
                return false;
            }

            $day = (int) substr($number, 4, 3);

            return (int) substr($number, 0, 4) >= 1900
                && (int) substr($number, 0, 4) <= (int) date('Y')
                && (($day >= 1 && $day <= 366) || ($day >= 501 && $day <= 866));
        }

        return preg_match('/^[A-Z0-9]{7,12}$/', $number) === 1;
    }

    private function nicRegistrationExists(string $documentNumber, ?string $exceptVerificationId = null): bool
    {
        return app(VisitorRegistrationResumeService::class)
            ->findByNic($documentNumber, $exceptVerificationId) !== null;
    }

    private function duplicateNicMessage(): string
    {
        return 'This NIC number is already registered for this event. Only one registration is allowed per NIC.';
    }

    private function hasCompleteIdentityFields(array $verification): bool
    {
        if (blank(data_get($verification, 'document_number'))
            || blank(data_get($verification, 'full_name'))) {
            return false;
        }

        return data_get($verification, 'document_type') === 'passport'
            || filled(data_get($verification, 'address'));
    }

    /** Require a current admin-configured event day when an active event exists. */
    private function registrationDayRedirect(Request $request): ?RedirectResponse
    {
        if (! Schema::hasTable('event_configurations')) {
            return null;
        }

        $event = EventConfiguration::query()
            ->where('singleton_key', EventConfiguration::SINGLETON_KEY)
            ->where('is_active', true)
            ->first();

        if (! $event) {
            return null;
        }

        $selectedId = data_get($request->session()->get('event_registration_day'), 'id');
        $day = $selectedId
            ? EventRegistrationDay::query()
                ->whereKey($selectedId)
                ->where('event_configuration_id', $event->id)
                ->first()
            : null;

        if ($day && $day->isOpenForRegistration()) {
            return null;
        }

        $request->session()->forget('event_registration_day');

        return redirect()->route('visitor.registration-days')->withErrors([
            'registration_day' => 'Choose an available event day before starting registration.',
        ]);
    }

    /** Resolve the active category that owns the public participant registration fee. */
    private function selfRegistrationCategory(Request $request): ?VisitorCategory
    {
        if (! Schema::hasTable('visitor_categories')) {
            return null;
        }

        $sessionCategory = $request->session()->get('visitor_category', []);
        $categoryId = data_get($sessionCategory, 'id');
        if (filled($categoryId)) {
            $category = VisitorCategory::query()
                ->whereKey($categoryId)
                ->where('is_active', true)
                ->first();
            if ($category) {
                return $category;
            }
        }

        return VisitorCategory::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['participant'])
                    ->orWhereRaw('LOWER(code) = ?', ['participant']);
            })
            ->first();
    }

    private function sameIdentityName(string $left, string $right): bool
    {
        $normalise = fn (string $value): string => mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value));

        return $normalise($left) !== '' && $normalise($left) === $normalise($right);
    }

    private function maskDocumentNumber(string $value): string
    {
        $value = preg_replace('/\s+/', '', $value);

        return $value === '' ? '' : str_repeat('*', max(0, strlen($value) - 3)).substr($value, -3);
    }
}
