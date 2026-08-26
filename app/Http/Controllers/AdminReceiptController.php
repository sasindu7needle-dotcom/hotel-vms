<?php

namespace App\Http\Controllers;

use App\Models\VerifiedVisitor;
use App\Services\PaymentConfirmationEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminReceiptController extends Controller
{
    public function __construct(private PaymentConfirmationEmailService $paymentEmail)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'visitor_id' => ['nullable', 'integer'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $visitor = null;
        $matches = collect();

        if ($search !== '') {
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

        return view('admin.receipts.index', compact('search', 'visitor', 'matches'));
    }

    public function confirm(Request $request, VerifiedVisitor $visitor): RedirectResponse
    {
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
}
