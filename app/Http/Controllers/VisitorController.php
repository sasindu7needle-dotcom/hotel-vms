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
use App\Services\VisitorMediaService;
use App\Services\GeminiDocumentService;
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
    public function registrationDays()
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

        return view('visitor.registration_days', compact('eventConfiguration', 'registrationDays'));
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

        $request->session()->forget([
            'verification',
            'didit_verification',
            'visitor_registration',
        ]);
        $request->session()->put('event_registration_day', [
            'id' => $registrationDay->id,
            'label' => $registrationDay->label,
            'event_date' => $registrationDay->event_date->format('Y-m-d'),
            'entrance_fee' => $registrationDay->entrance_fee,
        ]);

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
            'document_type' => ['required', 'in:nic,driving_license,passport'],
            'identity_verification_id' => ['required', 'uuid'],
            'mobile_number' => ['required', 'regex:/^(?:\+94|94|0)?7\d{8}$/'],
            'whatsapp_number' => ['nullable', 'regex:/^(?:\+94|94|0)?7\d{8}$/'],
            'address' => ['required', 'string', 'max:500'],
            'occupation' => ['required', 'string', 'max:100'],
            'company' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:visitor_categories,id'],
            'entrance_fee' => ['required', 'numeric', 'min:0', 'max:9999999999'],
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
        $facePhoto = $this->storeManualImage($request->file('face_photo'), $verificationId.'-face');
        $category = ! empty($validated['category_id']) ? VisitorCategory::find($validated['category_id']) : null;

        $visitor = $this->persistVerifiedVisitor([
            'verification_id' => $verificationId,
            'document_type' => $validated['document_type'],
            'document_number' => data_get($identity, 'document_number'),
            'full_name' => $validated['full_name'],
            'full_name_latin' => $validated['full_name'],
            'address' => $validated['address'],
            'address_latin' => $validated['address'],
            'mobile_number' => $this->normaliseSriLankanPhone($validated['mobile_number']),
            'whatsapp_number' => $this->normaliseSriLankanPhone($validated['whatsapp_number'] ?: $validated['mobile_number']),
            'occupation' => $validated['occupation'],
            'company' => $exhibitorProfile?->company_name ?: $validated['company'],
            'category' => $exhibitorProfile ? 'Exhibitor' : ($category?->name ?: 'Manual registration'),
            'visitor_category_id' => $exhibitorProfile ? null : $category?->id,
            'exhibitor_profile_id' => $exhibitorProfile?->id,
            'entrance_fee' => $exhibitorProfile ? 0 : $validated['entrance_fee'],
            'photo_path' => data_get($identity, 'photo_path'),
            'photo_mime' => data_get($identity, 'photo_mime'),
            'back_photo_path' => data_get($identity, 'back_photo_path'),
            'back_photo_mime' => data_get($identity, 'back_photo_mime'),
            'selfie_path' => $facePhoto['path'],
            'selfie_mime' => $facePhoto['mime'],
            'identity_reviewed_at' => now(),
            'verified_at' => now(),
            'ocr_provider' => 'manual_registration',
        ], ['face_verification_status' => 'manual_review']);

        $request->session()->put('visitor_registration', [
            'record_id' => $visitor->id,
            'verification_id' => $visitor->verification_id,
            'full_name' => $visitor->full_name,
            'category' => $visitor->category,
            'photo_path' => $visitor->photo_path,
            'photo_mime' => $visitor->photo_mime,
            'selfie_path' => $visitor->selfie_path,
            'selfie_mime' => $visitor->selfie_mime,
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
    public function confirm(Request $request)
    {
        if ($redirect = $this->registrationDayRedirect($request)) {
            return $redirect;
        }

        $verification = $request->session()->get('verification', $request->session()->get('didit_verification', []));
        $category = $request->session()->get('visitor_category', []);
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
            'address' => 'required|string|max:500',
            'mobile_number' => ['required', 'regex:/^[0-9]{9}$/'],
            'same_as_mobile' => 'nullable|boolean',
            'whatsapp_number' => ['required_unless:same_as_mobile,1', 'nullable', 'regex:/^[0-9]{9}$/'],
            'occupation' => 'required|string|max:100',
            'company' => 'required|string|max:150',
        ], [
            'mobile_number.regex' => 'Enter a 9-digit number after +94.',
            'whatsapp_number.regex' => 'Enter a 9-digit number after +94.',
        ]);

        if ($validated['document_type'] === 'nic' && ! $request->boolean('name_confirmation')) {
            return back()->withInput()->withErrors([
                'name_confirmation' => 'Confirm that the English spelling of your name is correct.',
            ]);
        }

        $details = array_merge($validated, [
            'verification_id' => data_get($verification, 'verification_id', data_get($verification, 'session_id')),
            'didit_session_id' => data_get($verification, 'verification_id', data_get($verification, 'session_id')),
            'full_name' => $validated['full_name'],
            'full_name_latin' => $validated['full_name'],
            'sinhala_name' => data_get($verification, 'sinhala_name'),
            'tamil_name' => data_get($verification, 'tamil_name'),
            'printed_english_name' => data_get($verification, 'printed_english_name'),
            'suggested_english_name' => data_get($verification, 'suggested_english_name'),
            'sinhala_transliteration' => data_get($verification, 'sinhala_transliteration'),
            'tamil_transliteration' => data_get($verification, 'tamil_transliteration'),
            'english_name_alternatives' => data_get($verification, 'english_name_alternatives', []),
            'name_review_status' => $validated['document_type'] === 'nic'
                ? ($this->sameIdentityName($validated['full_name'], (string) data_get($verification, 'suggested_english_name')) ? 'confirmed' : 'corrected')
                : 'not_required',
            'document_number' => strtoupper(preg_replace('/\s+/', '', $validated['document_number'])),
            'address' => $validated['address'],
            'address_latin' => $validated['address'],
            'photo_url' => data_get($verification, 'photo_url')
                ?: route('visitor.session_photo', ['type' => data_get($verification, 'selfie_path') ? 'selfie' : 'photo']),
            'photo_path' => data_get($verification, 'photo_path'),
            'photo_mime' => data_get($verification, 'photo_mime'),
            'back_photo_path' => data_get($verification, 'back_photo_path'),
            'back_photo_mime' => data_get($verification, 'back_photo_mime'),
            'selfie_path' => data_get($verification, 'selfie_path'),
            'selfie_mime' => data_get($verification, 'selfie_mime'),
            'ocr_provider' => data_get($verification, 'provider'),
            'identity_reviewed_at' => now()->toIso8601String(),
            'verified_at' => data_get($verification, 'verified_at'),
            'whatsapp_number' => $request->boolean('same_as_mobile')
                ? $validated['mobile_number']
                : $validated['whatsapp_number'],
            'category' => data_get($category, 'name', 'Not assigned'),
            'entrance_fee' => $registrationDay
                ? $registrationDay->entrance_fee
                : data_get($category, 'entrance_fee'),
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

        $resumeRoute = null;
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

            $request->session()->put('visitor_registration', $details);

            if ($existingRegistration->payment_status === 'paid') {
                return redirect()->route('visitor.thank-you');
            }

            $resumeRoute = match ($existingRegistration->payment_method) {
                'cash' => 'visitor.payment.cash',
                'visa_master', 'amex' => 'visitor.payment.card',
                default => null,
            };
        }

        $request->session()->put('visitor_registration', $details);
        try {
            $visitor = $this->persistVerifiedVisitor($details);
        } catch (\Throwable $exception) {
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

        return $resumeRoute
            ? redirect()->route($resumeRoute)
            : redirect()->route('visitor.confirm.show');
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

        $pathKey = in_array($type, ['selfie', 'photo', 'back_photo']) ? $type.'_path' : 'selfie_path';
        $mimeKey = in_array($type, ['selfie', 'photo', 'back_photo']) ? $type.'_mime' : 'selfie_mime';

        $path = data_get($registration, $pathKey, data_get($verification, $pathKey));
        $mime = data_get($registration, $mimeKey, data_get($verification, $mimeKey, 'image/jpeg'));

        // Fallback if selfie path is empty
        if (blank($path) && $type === 'selfie') {
            $path = data_get($registration, 'photo_path', data_get($verification, 'photo_path'));
            $mime = data_get($registration, 'photo_mime', data_get($verification, 'photo_mime', 'image/jpeg'));
        }

        $media = app(VisitorMediaService::class);
        if (blank($path) || ! $media->exists($path)) {
            abort(404);
        }

        return $media->response($path, $mime, [
            'Cache-Control' => 'no-cache, private',
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
            'payment_method' => 'required|in:visa_master,amex,cash',
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

        return $validated['payment_method'] === 'cash'
            ? redirect()->route('visitor.payment.cash')
            : redirect()->route('visitor.payment.card');
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
    public function downloadCard(Request $request)
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
        $qrCode = preg_replace('/<\?xml[^>]*\?>/i', '', (string) QrCode::format('svg')
            ->size(250)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($qrPayload));
        $photoDataUri = filled($visitor->selfie_path)
            ? app(VisitorMediaService::class)->dataUri($visitor->selfie_path, $visitor->selfie_mime)
            : null;
        $cardStatus = $visitor->payment_status === 'paid' ? 'VERIFIED' : 'PAYMENT PENDING';
        $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('img/logo.png')));

        $svg = view('visitor.card_download', compact(
            'visitor',
            'eventName',
            'qrPayload',
            'qrCode',
            'photoDataUri',
            'cardStatus',
            'logoDataUri'
        ))->render();
        $safeName = Str::slug($visitor->full_name ?: 'visitor') ?: 'visitor';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$safeName.'-entrance-card.svg"',
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
        $paymentReference = data_get($details, 'payment_reference')
            ?: 'VMS-'.now()->format('Ymd').'-'.str_pad((string) $visitor->id, 6, '0', STR_PAD_LEFT);
        $qrPayload = (string) ($visitor->verification_id ?: $paymentReference ?: Str::uuid());
        $qrCode = QrCode::format('svg')
            ->size(220)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($qrPayload);

        return view('visitor.thank_you', compact(
            'details',
            'visitor',
            'eventName',
            'paymentReference',
            'qrCode',
            'qrPayload'
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
            'address' => data_get($details, 'address'),
            'address_latin' => data_get($details, 'address_latin'),
            'mobile_number' => '+94'.data_get($details, 'mobile_number'),
            'whatsapp_number' => '+94'.data_get($details, 'whatsapp_number'),
            'occupation' => data_get($details, 'occupation'),
            'company' => data_get($details, 'company'),
            'photo_url' => data_get($details, 'photo_url'),
            'photo_path' => data_get($details, 'photo_path'),
            'photo_mime' => data_get($details, 'photo_mime'),
            'back_photo_path' => data_get($details, 'back_photo_path'),
            'back_photo_mime' => data_get($details, 'back_photo_mime'),
            'selfie_path' => data_get($details, 'selfie_path'),
            'selfie_mime' => data_get($details, 'selfie_mime'),
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
