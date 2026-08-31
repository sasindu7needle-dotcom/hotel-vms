<?php

namespace App\Services;

use App\Mail\PaymentConfirmationMail;
use App\Models\DirectPayPayment;
use App\Models\VerifiedVisitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaymentConfirmationEmailService
{
    public function __construct(
        private EntranceCardImageService $cardImage,
        private PaymentInvoicePdfService $invoicePdf,
        private VisitorMediaService $visitorMedia,
    ) {
    }

    /**
     * Send the confirmation once, after payment is canonical in the database.
     * A row lock prevents duplicate gateway callbacks from sending duplicate mail.
     */
    public function sendIfNeeded(VerifiedVisitor $visitor, ?DirectPayPayment $payment = null): bool
    {
        return $this->send($visitor, $payment, false);
    }

    /** Explicitly resend a paid visitor's confirmation from the admin directory. */
    public function resend(VerifiedVisitor $visitor, ?DirectPayPayment $payment = null): bool
    {
        return $this->send($visitor, $payment, true);
    }

    private function send(VerifiedVisitor $visitor, ?DirectPayPayment $payment, bool $force): bool
    {
        if (! filter_var($visitor->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return DB::transaction(function () use ($visitor, $payment, $force) {
            $lockedVisitor = VerifiedVisitor::query()
                ->with(['eventRegistrationDay.eventConfiguration', 'exhibitorProfile', 'visitorCategory'])
                ->lockForUpdate()
                ->findOrFail($visitor->id);

            if ($lockedVisitor->payment_status !== 'paid'
                || (! $force && $lockedVisitor->payment_confirmation_emailed_at)) {
                return false;
            }

            $resolvedPayment = filled($payment?->id)
                ? DirectPayPayment::find($payment->id)
                : $lockedVisitor->directPayPayments()->where('status', 'paid')->latest('id')->first();
            $invoice = $this->invoicePdf->details($lockedVisitor, $resolvedPayment);
            $eventName = $invoice['event_name'];
            $qrPayload = (string) ($lockedVisitor->verification_id ?: $lockedVisitor->id);
            $photoDataUri = filled($lockedVisitor->selfie_path)
                ? $this->visitorMedia->dataUri($lockedVisitor->selfie_path, $lockedVisitor->selfie_mime)
                : null;
            $entranceCardPng = $this->cardImage->render(
                $lockedVisitor,
                $eventName,
                $qrPayload,
                'VERIFIED',
                $photoDataUri,
            );
            $invoicePdf = $this->invoicePdf->render($invoice);

            Mail::to($lockedVisitor->email, $lockedVisitor->full_name)->send(
                new PaymentConfirmationMail($lockedVisitor, $invoice, $entranceCardPng, $invoicePdf)
            );

            $lockedVisitor->forceFill(['payment_confirmation_emailed_at' => now()])->save();

            return true;
        }, 3);
    }
}
