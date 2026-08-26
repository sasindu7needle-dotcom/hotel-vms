<?php

namespace App\Http\Controllers;

use App\Models\VerifiedVisitor;
use App\Services\PaymentConfirmationEmailService;
use App\Services\VisitorMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminReceiptController extends Controller
{
    public function __construct(
        private PaymentConfirmationEmailService $paymentEmail,
        private VisitorMediaService $visitorMedia,
    ) {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'visitor_id' => ['nullable', 'integer'],
            'manual_id' => ['nullable', 'integer'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $visitor = null;
        $matches = collect();
        $manualRegistrations = VerifiedVisitor::query()
            ->where('ocr_provider', 'manual_registration')
            ->whereNull('exhibitor_profile_id')
            ->latest()
            ->limit(50)
            ->get();

        if (! empty($validated['manual_id'])) {
            $visitor = $manualRegistrations->firstWhere('id', (int) $validated['manual_id']);
        } elseif ($search !== '') {
            $normalized = strtoupper(preg_replace('/\s+/', '', $search));
            $matches = VerifiedVisitor::query()
                ->with('eventRegistrationDay')
                ->where(function ($query) use ($search, $normalized) {
                    $query->where('document_number', $normalized)
                        ->orWhere('mobile_number', $search);
                })
                ->latest()
                ->limit(20)
                ->get();
            $visitor = $matches->firstWhere('id', (int) ($validated['visitor_id'] ?? 0))
                ?: $matches->first();
        }

        return view('admin.receipts.index', compact('search', 'visitor', 'matches', 'manualRegistrations'));
    }

    public function confirm(Request $request, VerifiedVisitor $visitor): RedirectResponse
    {
        abort_if(
            $visitor->ocr_provider === 'manual_registration' && $visitor->exhibitor_profile_id === null,
            404
        );

        $validated = $request->validate([
            'entrance_fee' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'payment_method' => ['required', 'in:cash,visa_master,amex'],
        ]);

        $visitor->update([
            'entrance_fee' => $validated['entrance_fee'],
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'paid',
            'paid_at' => $visitor->paid_at ?: now(),
            'registration_status' => 'paid',
        ]);

        try {
            $this->paymentEmail->sendIfNeeded($visitor->fresh());
        } catch (\Throwable $exception) {
            Log::error('Payment confirmation email could not be sent.', [
                'visitor_id' => $visitor->id,
                'exception_class' => $exception::class,
            ]);
        }

        return redirect()
            ->route('admin.receipts.index', [
                'search' => $visitor->document_number ?: $visitor->mobile_number,
                'visitor_id' => $visitor->id,
            ])
            ->with('status', 'Payment confirmed for '.$visitor->full_name.'.');
    }

    public function uploadPaymentSlip(Request $request, VerifiedVisitor $visitor): RedirectResponse
    {
        abort_unless(
            $visitor->ocr_provider === 'manual_registration' && $visitor->exhibitor_profile_id === null,
            404
        );

        $validated = $request->validate([
            'payment_slip' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);
        $file = $validated['payment_slip'];
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $filename = ($visitor->verification_id ?: 'visitor-'.$visitor->id).'-payment-slip.'.$extension;
        $path = $this->visitorMedia->storeAs($file, 'verified-visitors', $filename);

        if (! is_string($path) || $path === '') {
            return back()->withErrors(['payment_slip' => 'The payment slip could not be stored.']);
        }

        $visitor->update([
            'payment_slip_path' => $path,
            'payment_slip_mime' => $mime,
            'payment_slip_uploaded_at' => now(),
            'payment_method' => $visitor->payment_method ?: 'cash',
            'payment_status' => 'paid',
            'paid_at' => $visitor->paid_at ?: now(),
            'registration_status' => 'paid',
        ]);

        $emailDelivered = false;
        try {
            $emailDelivered = $this->paymentEmail->sendIfNeeded($visitor->fresh())
                || $visitor->fresh()->payment_confirmation_emailed_at !== null;
        } catch (\Throwable $exception) {
            Log::error('Manual payment confirmation email could not be sent.', [
                'visitor_id' => $visitor->id,
                'exception_class' => $exception::class,
            ]);
        }

        $redirect = redirect()
            ->route('admin.receipts.index', ['manual_id' => $visitor->id]);

        if (! $emailDelivered) {
            return $redirect
                ->with('status', 'Payment confirmed for '.$visitor->full_name.'.')
                ->withErrors(['email' => 'The entrance card and invoice email could not be sent. Check the visitor email address and upload the slip again to retry.']);
        }

        return $redirect->with('status', 'Payment confirmed for '.$visitor->full_name.'. The entrance card and invoice were emailed successfully.');
    }
}
