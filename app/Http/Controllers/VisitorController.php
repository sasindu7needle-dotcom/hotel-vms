<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Visitor;
use App\Models\VerifiedVisitor;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        ]);

        return redirect()->route('visitor.create');
    }

    /**
     * Display the visitor registration form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function create(Request $request)
    {
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
                ->withErrors(['verification' => 'OCR did not read the full name and address. Please upload clearer document photos and verify again.']);
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
        $type = $request->query('type', 'nic');
        $validTypes = ['nic', 'driving_license', 'passport'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'nic';
        }

        return view('visitor.upload_document', compact('type'));
    }

    public function showPhotoCapture(Request $request)
    {
        $verification = $request->session()->get('verification', []);

        if (! is_array($verification) || blank(data_get($verification, 'session_id'))) {
            return redirect()->route('visitor.upload_document')->withErrors([
                'verification' => 'Upload and verify your identity document first.',
            ]);
        }

        if (! $this->hasCompleteIdentityFields($verification)) {
            return redirect()->route('visitor.upload_document', ['type' => data_get($verification, 'document_type', 'nic')])
                ->withErrors(['verification' => 'OCR did not read the full name and address. Please upload clearer document photos and verify again.']);
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
        $verification = $request->session()->get('verification', $request->session()->get('didit_verification', []));
        $category = $request->session()->get('visitor_category', []);

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

        $details = array_merge($validated, [
            'verification_id' => data_get($verification, 'verification_id', data_get($verification, 'session_id')),
            'didit_session_id' => data_get($verification, 'verification_id', data_get($verification, 'session_id')),
            'full_name' => $validated['full_name'],
            'full_name_latin' => $validated['full_name'],
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
            'entrance_fee' => data_get($category, 'entrance_fee'),
        ]);

        $request->session()->put('visitor_registration', $details);
        $visitor = $this->persistVerifiedVisitor($details);
        $request->session()->put('visitor_registration.record_id', $visitor->id);

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

        if (blank($path) || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mime ?: 'image/jpeg',
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
        $paymentStatus = $validated['payment_method'] === 'cash' ? 'cash_pending' : 'card_pending';
        $visitor = $this->persistVerifiedVisitor($details, [
            'payment_method' => $validated['payment_method'],
            'payment_status' => $paymentStatus,
        ]);
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
        if (! is_array($details) || data_get($details, 'payment_status') !== 'paid') {
            return redirect()->route('visitor.create');
        }

        $visitor = VerifiedVisitor::find(data_get($details, 'record_id'));
        if (! $visitor || $visitor->payment_status !== 'paid') {
            return redirect()->route('visitor.create');
        }

        $eventName = config('vms.event_name');
        $paymentReference = data_get($details, 'payment_reference');
        $qrPayload = (string) ($visitor->verification_id ?: $paymentReference ?: Str::uuid());
        $qrCode = QrCode::format('svg')
            ->size(220)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($qrPayload);

        return view('visitor.thank_you', compact(
            'details',
            'eventName',
            'paymentReference',
            'qrCode',
            'qrPayload'
        ));
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

    private function hasCompleteIdentityFields(array $verification): bool
    {
        return filled(data_get($verification, 'document_number'))
            && filled(data_get($verification, 'full_name'))
            && filled(data_get($verification, 'address'));
    }
}
