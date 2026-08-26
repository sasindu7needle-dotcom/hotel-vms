<?php

namespace App\Mail;

use App\Models\VerifiedVisitor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

class PaymentConfirmationMail extends Mailable
{
    use Queueable;

    public string $entranceCardFilename;

    public string $invoiceFilename;

    /**
     * @param array<string, string> $invoice
     */
    public function __construct(
        public VerifiedVisitor $visitor,
        public array $invoice,
        private string $entranceCardPng,
        private string $invoicePdf,
    ) {
        $safeName = Str::slug($visitor->full_name ?: 'visitor') ?: 'visitor';
        $safeInvoice = Str::slug($invoice['invoice_number']) ?: 'payment-invoice';
        $this->entranceCardFilename = $safeName.'-entrance-card.png';
        $this->invoiceFilename = $safeInvoice.'.pdf';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment confirmed - '.$this->invoice['event_name']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-confirmation');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->entranceCardPng, $this->entranceCardFilename)
                ->withMime('image/png'),
            Attachment::fromData(fn () => $this->invoicePdf, $this->invoiceFilename)
                ->withMime('application/pdf'),
        ];
    }
}
