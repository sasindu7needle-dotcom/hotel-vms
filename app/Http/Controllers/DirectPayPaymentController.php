<?php

namespace App\Http\Controllers;

use App\Models\DirectPayPayment;
use App\Models\VerifiedVisitor;
use App\Services\DirectPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class DirectPayPaymentController extends Controller
{
    public function __construct(private DirectPayService $directPay)
    {
    }

    public function showStart(Request $request)
    {
        $details = $request->session()->get('visitor_registration');
        if (! is_array($details)
            || ! in_array(data_get($details, 'payment_method'), ['visa_master', 'amex'], true)
            || blank(data_get($details, 'record_id'))) {
            return redirect()->route('visitor.create');
        }

        $visitor = VerifiedVisitor::find(data_get($details, 'record_id'));
        if (! $visitor) {
            return redirect()->route('visitor.create')->withErrors([
                'registration' => 'Your registration session has expired. Please register again.',
            ]);
        }

        if ($visitor->payment_status === 'paid') {
            $this->syncPaidSession($request, $visitor);

            return redirect()->route('visitor.thank-you');
        }

        return view('visitor.payment.card', [
            'details' => $details,
            'visitor' => $visitor,
            'payment' => null,
            'directPayConfig' => null,
            'directPayConfigured' => $this->directPay->isConfigured(),
        ]);
    }

    public function start(Request $request, VerifiedVisitor $visitor)
    {
        $this->authorizeSessionVisitor($request, $visitor);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:100'],
            'mobile' => ['required', 'regex:/^(?:\+94|94|0)?7\d{8}$/'],
        ], [
            'mobile.regex' => 'Enter a DirectPay-supported Sri Lankan mobile number, for example +94771234567.',
        ]);

        if (! $this->directPay->isConfigured()) {
            return back()->withErrors([
                'payment' => 'Card payments are temporarily unavailable. Please contact reception.',
            ]);
        }

        $result = DB::transaction(function () use ($visitor, $validated) {
            $lockedVisitor = VerifiedVisitor::whereKey($visitor->id)->lockForUpdate()->firstOrFail();
            if ($lockedVisitor->payment_status === 'paid') {
                return ['paid' => true];
            }

            if (! is_numeric($lockedVisitor->entrance_fee) || (float) $lockedVisitor->entrance_fee <= 0) {
                return ['error' => 'The entrance fee is invalid. Please contact reception.'];
            }

            $pending = DirectPayPayment::where('verified_visitor_id', $lockedVisitor->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            $lockedVisitor->update([
                'email' => $validated['email'],
                'mobile_number' => $this->normalizeMobile($validated['mobile']),
                'payment_status' => 'pending',
                'registration_status' => 'payment_pending',
            ]);

            $payment = $pending ?: DirectPayPayment::create([
                'verified_visitor_id' => $lockedVisitor->id,
                'reference' => $this->directPay->generateReference($lockedVisitor->id),
                'expected_amount' => $lockedVisitor->entrance_fee,
                'currency' => $this->directPay->currency(),
                'status' => 'pending',
            ]);

            return ['payment' => $payment];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['payment' => $result['error']]);
        }

        if (isset($result['paid'])) {
            $this->syncPaidSession($request, $visitor->fresh());

            return redirect()->route('visitor.thank-you');
        }

        $payment = $result['payment'];
        $request->session()->put('visitor_registration.payment_reference', $payment->reference);
        $request->session()->put('visitor_registration.payment_status', 'pending');

        return redirect()->route('visitor.payment.directpay.checkout', $payment->reference);
    }

    public function checkout(Request $request, string $reference)
    {
        $payment = DirectPayPayment::with('visitor')->where('reference', $reference)->firstOrFail();
        $this->authorizeSessionVisitor($request, $payment->visitor);

        if ($payment->visitor->payment_status === 'paid') {
            return redirect()->route('visitor.payment.directpay.status', $payment->reference);
        }

        if ($payment->status !== 'pending') {
            return redirect()->route('visitor.payment.directpay.status', $payment->reference);
        }

        if (! $this->hasValidDirectPayMobile($payment->visitor->mobile_number)) {
            return redirect()->route('visitor.payment.card')->withErrors([
                'mobile' => 'DirectPay rejected the saved mobile number. Enter a supported number and try again.',
            ]);
        }

        if (! $this->directPay->isConfigured()) {
            return redirect()->route('visitor.payment.card')->withErrors([
                'payment' => 'Card payments are temporarily unavailable. Please contact reception.',
            ]);
        }

        return view('visitor.payment.card', [
            'details' => $request->session()->get('visitor_registration', []),
            'visitor' => $payment->visitor,
            'payment' => $payment,
            'directPayConfig' => $this->directPay->checkoutConfiguration($payment),
            'directPayConfigured' => true,
        ]);
    }

    public function status(Request $request, string $reference)
    {
        $payment = DirectPayPayment::with('visitor')->where('reference', $reference)->firstOrFail();
        $this->authorizeSessionVisitor($request, $payment->visitor);

        if ($payment->status === 'paid' && $payment->visitor->payment_status === 'paid') {
            $this->syncPaidSession($request, $payment->visitor, $payment->reference);

            return redirect()->route('visitor.thank-you');
        }

        return view('visitor.payment.status', compact('payment'));
    }

    public function legacyConfirmation(Request $request)
    {
        $visitorId = data_get($request->session()->get('visitor_registration'), 'record_id');
        $payment = filled($visitorId)
            ? DirectPayPayment::where('verified_visitor_id', $visitorId)->latest('id')->first()
            : null;

        return $payment
            ? redirect()->route('visitor.payment.directpay.status', $payment->reference)
            : redirect()->route('visitor.payment.card');
    }

    public function confirmation(Request $request)
    {
        $requestBody = $request->getContent();

        if (! $this->directPay->isConfigured()) {
            Log::error('DirectPay confirmation cannot be verified because the gateway is not configured.');

            return response()->json(['message' => 'Payment verification unavailable.'], 503);
        }

        if (! $this->directPay->verifyCallbackSignature($requestBody, $request->header('Authorization'))) {
            Log::warning('DirectPay confirmation signature verification failed.');

            return response()->json(['message' => 'Invalid callback signature.'], 401);
        }

        try {
            $payload = $this->directPay->decodeCallback($requestBody);
        } catch (InvalidArgumentException $exception) {
            Log::warning('DirectPay confirmation payload could not be decoded.', [
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid callback payload.'], 400);
        }

        $reference = trim((string) data_get($payload, 'order_id', ''));
        $transactionId = trim((string) data_get($payload, 'transaction_id', ''));
        $callbackStatus = strtoupper(trim((string) data_get($payload, 'transaction.status', '')));
        $callbackCurrency = strtoupper(trim((string) data_get($payload, 'transaction.currency', '')));
        $callbackAmount = data_get($payload, 'transaction.amount');

        Log::info('DirectPay confirmation received.', [
            'reference' => $reference,
            'transaction_id' => $transactionId,
            'status' => $callbackStatus,
        ]);

        if ($reference === '' || $transactionId === '') {
            return response()->json(['message' => 'Missing payment identifiers.'], 422);
        }

        $payment = DirectPayPayment::where('reference', $reference)->first();
        if (! $payment) {
            Log::warning('DirectPay confirmation used an unknown reference.', ['reference' => $reference]);

            return response()->json(['message' => 'Unknown payment reference.'], 404);
        }

        if (! hash_equals($payment->currency, $callbackCurrency)
            || ! $this->directPay->amountsMatch($payment->expected_amount, $callbackAmount)) {
            Log::warning('DirectPay callback amount or currency mismatch.', ['reference' => $reference]);

            return response()->json(['message' => 'Callback payment details mismatch.'], 422);
        }

        $status = $this->directPay->normalizeStatus($callbackStatus);
        try {
            DB::transaction(function () use ($payment, $transactionId, $status, $payload, $callbackStatus, $callbackAmount, $callbackCurrency) {
                $lockedPayment = DirectPayPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $visitor = VerifiedVisitor::whereKey($lockedPayment->verified_visitor_id)->lockForUpdate()->firstOrFail();

                $transactionConflict = DirectPayPayment::where('gateway_transaction_id', $transactionId)
                    ->whereKeyNot($lockedPayment->id)
                    ->exists();
                if ($transactionConflict) {
                    throw new RuntimeException('DirectPay transaction ID is already linked to another payment.');
                }

                // A paid attempt is final and is never downgraded by a later callback.
                if ($lockedPayment->status !== 'paid') {
                    $lockedPayment->update([
                        'gateway_transaction_id' => $transactionId,
                        'gateway_status' => $callbackStatus,
                        'status' => $status,
                        'safe_gateway_response' => [
                            'transaction_id' => $transactionId,
                            'status' => $callbackStatus,
                            'description' => (string) data_get($payload, 'transaction.description', ''),
                            'date_time' => data_get($payload, 'transaction.date_time', data_get($payload, 'transaction.dateTime')),
                            'amount' => (string) $callbackAmount,
                            'currency' => $callbackCurrency,
                            'type' => (string) data_get($payload, 'type', ''),
                        ],
                        'verified_at' => now(),
                    ]);
                }

                if ($status === 'paid') {
                    if ($visitor->payment_status !== 'paid') {
                        $visitor->update([
                            'payment_status' => 'paid',
                            'paid_at' => $visitor->paid_at ?: now(),
                            'registration_status' => 'registered',
                        ]);
                    }

                    return;
                }

                $latestAttemptId = DirectPayPayment::where('verified_visitor_id', $visitor->id)->max('id');
                if ($visitor->payment_status !== 'paid' && (int) $latestAttemptId === $lockedPayment->id) {
                    $visitor->update([
                        'payment_status' => $status,
                        'registration_status' => 'payment_pending',
                    ]);
                }
            });
        } catch (RuntimeException $exception) {
            Log::warning('DirectPay confirmation transaction conflict.', [
                'reference' => $reference,
                'transaction_id' => $transactionId,
            ]);

            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['received' => true, 'status' => $status]);
    }

    private function authorizeSessionVisitor(Request $request, VerifiedVisitor $visitor): void
    {
        abort_unless(
            (int) data_get($request->session()->get('visitor_registration'), 'record_id') === $visitor->id,
            403
        );
    }

    private function syncPaidSession(Request $request, VerifiedVisitor $visitor, ?string $reference = null): void
    {
        $request->session()->put('visitor_registration.payment_status', 'paid');
        $request->session()->put(
            'visitor_registration.payment_reference',
            $reference ?: data_get($request->session()->get('visitor_registration'), 'payment_reference')
                ?: 'VMS-'.now()->format('Ymd').'-'.str_pad((string) $visitor->id, 6, '0', STR_PAD_LEFT)
        );
    }

    private function normalizeMobile(string $mobile): string
    {
        $digits = (string) preg_replace('/\D+/', '', $mobile);
        if (str_starts_with($digits, '94')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '+94'.$digits;
    }

    private function hasValidDirectPayMobile(?string $mobile): bool
    {
        return preg_match('/^\+947\d{8}$/', (string) $mobile) === 1;
    }
}
