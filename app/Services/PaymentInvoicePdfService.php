<?php

namespace App\Services;

use App\Models\DirectPayPayment;
use App\Models\VerifiedVisitor;
use Dompdf\Dompdf;
use Dompdf\Options;

class PaymentInvoicePdfService
{
    /** @return array<string, string> */
    public function details(VerifiedVisitor $visitor, ?DirectPayPayment $payment = null): array
    {
        $paidAt = $visitor->paid_at ?: now();
        $reference = $payment?->reference
            ?: 'VMS-'.$paidAt->format('Ymd').'-'.str_pad((string) $visitor->id, 6, '0', STR_PAD_LEFT);
        $eventName = $visitor->eventRegistrationDay?->eventConfiguration?->event_name
            ?: (string) config('vms.event_name');
        $eventDate = $visitor->eventRegistrationDay
            ? $visitor->eventRegistrationDay->label.' - '.$visitor->eventRegistrationDay->event_date->format('d F Y')
            : 'As shown on the entrance card';

        return [
            'invoice_number' => 'INV-'.$reference,
            'payment_reference' => $reference,
            'transaction_id' => $payment?->gateway_transaction_id ?: 'Not applicable',
            'visitor_name' => $visitor->full_name ?: 'Registered visitor',
            'visitor_email' => (string) $visitor->email,
            'participant_reference' => (string) ($visitor->verification_id ?: $visitor->id),
            'event_name' => $eventName,
            'event_date' => $eventDate,
            'payment_method' => $this->paymentMethod((string) $visitor->payment_method),
            'paid_at' => $paidAt->format('d F Y, h:i A'),
            'currency' => $payment?->currency ?: 'LKR',
            'amount' => number_format((float) ($payment?->expected_amount ?? $visitor->entrance_fee), 2),
        ];
    }

    /** @param array<string, string> $invoice */
    public function render(array $invoice): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.payment-invoice', compact('invoice'))->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    private function paymentMethod(string $method): string
    {
        return match ($method) {
            'visa_master' => 'Visa / Mastercard',
            'amex' => 'American Express',
            'cash' => 'Cash',
            default => ucfirst(str_replace('_', ' ', $method ?: 'Not specified')),
        };
    }
}
