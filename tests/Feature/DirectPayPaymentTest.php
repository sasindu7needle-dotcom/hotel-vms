<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmationMail;
use App\Models\DirectPayPayment;
use App\Models\VerifiedVisitor;
use App\Services\DirectPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DirectPayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sandbox-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.directpay', [
            'environment' => 'sandbox',
            'merchant_id' => 'TEST01',
            'api_key' => 'sandbox-api-key',
            'merchant_secret' => self::SECRET,
            'currency' => 'LKR',
        ]);
    }

    public function test_payment_start_uses_database_amount_and_v3_signed_payload(): void
    {
        $visitor = $this->visitor(['entrance_fee' => 5000]);

        $this->withSession(['visitor_registration' => $this->registration($visitor)])
            ->get(route('visitor.payment.card'))
            ->assertOk()
            ->assertSee('visitor@example.test')
            ->assertSee('+94771234567');

        $this->withSession(['visitor_registration' => $this->registration($visitor)])
            ->post(route('visitor.payment.directpay.start', $visitor), [
                'email' => 'ignored@example.test',
                'mobile' => '+441234567890',
                'amount' => '1.00',
            ])
            ->assertRedirect();

        $payment = DirectPayPayment::with('visitor')->firstOrFail();
        $this->assertSame('5000.00', $payment->expected_amount);
        $this->assertSame('LKR', $payment->currency);
        $this->assertSame('pending', $payment->status);
        $this->assertMatchesRegularExpression('/^DP-V\d+-[A-Z0-9]+$/', $payment->reference);
        $this->assertLessThanOrEqual(20, strlen($payment->reference));

        $configuration = app(DirectPayService::class)->checkoutConfiguration($payment);
        $payload = json_decode(base64_decode($configuration['dataString'], true), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('5000.00', $payload['amount']);
        $this->assertSame('ONE_TIME', $payload['type']);
        $this->assertSame($payment->reference, $payload['order_id']);
        $this->assertSame('LKR', $payload['currency']);
        $this->assertSame('visitor@example.test', $payload['email']);
        $this->assertSame('+94771234567', $payload['phone']);
        $this->assertSame(route('directpay.confirmation'), $payload['response_url']);
        $this->assertSame(hash_hmac('sha256', $configuration['dataString'], self::SECRET), $configuration['signature']);

        $this->withSession(['visitor_registration' => $this->registration($visitor)])
            ->get(route('visitor.payment.directpay.checkout', $payment->reference))
            ->assertOk()
            ->assertSee('https://cdn.directpay.lk/v3/directpayipg.min.js', false)
            ->assertSee('DirectPayIpg.Init', false)
            ->assertSee('doInContainerCheckout', false)
            ->assertDontSee(self::SECRET);
    }

    public function test_authenticated_success_callback_marks_payment_and_visitor_paid(): void
    {
        [$visitor, $payment] = $this->pendingPayment();

        $this->sendCallback($this->callbackPayload($payment, 'SUCCESS', '5000.00'))
            ->assertOk()
            ->assertJson(['received' => true, 'status' => 'paid']);

        $this->assertDatabaseHas('directpay_payments', [
            'id' => $payment->id,
            'gateway_transaction_id' => '880',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('verified_visitors', [
            'id' => $visitor->id,
            'payment_status' => 'paid',
            'registration_status' => 'registered',
        ]);
        $this->assertNotNull($visitor->fresh()->paid_at);
    }

    public function test_success_callback_emails_png_card_and_pdf_invoice_exactly_once(): void
    {
        Mail::fake();
        [$visitor, $payment] = $this->pendingPayment();
        $callback = $this->callbackPayload($payment, 'SUCCESS', '5000.00');

        $this->sendCallback($callback)->assertOk();

        Mail::assertSent(PaymentConfirmationMail::class, function (PaymentConfirmationMail $mail) use ($visitor, $payment) {
            $mail->assertTo($visitor->email);
            $this->assertSame($payment->reference, $mail->invoice['payment_reference']);
            $this->assertSame('5,000.00', $mail->invoice['amount']);

            $attachments = collect($mail->attachments())->map(fn ($attachment) => $attachment->attachWith(
                fn () => null,
                fn ($data, $resolvedAttachment) => [
                    'data' => $data(),
                    'name' => $resolvedAttachment->as,
                    'mime' => $resolvedAttachment->mime,
                ],
            ));

            $card = $attachments->firstWhere('mime', 'image/png');
            $invoice = $attachments->firstWhere('mime', 'application/pdf');
            $this->assertSame('payment-visitor-entrance-card.png', $card['name'] ?? null);
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $card['data'] ?? '');
            $this->assertSame('inv-dp-v'.$visitor->id.'-a82f91.pdf', $invoice['name'] ?? null);
            $this->assertStringStartsWith('%PDF-', $invoice['data'] ?? '');

            return true;
        });
        $this->assertNotNull($visitor->fresh()->payment_confirmation_emailed_at);

        $this->sendCallback($callback)->assertOk();
        Mail::assertSent(PaymentConfirmationMail::class, 1);
    }

    public function test_successful_card_payment_shows_the_completion_card_and_downloads_png(): void
    {
        [$visitor, $payment] = $this->pendingPayment();
        $this->sendCallback($this->callbackPayload($payment, 'SUCCESS', '5000.00'))->assertOk();

        $registration = $this->registration($visitor->fresh());
        $registration['payment_reference'] = $payment->reference;

        $this->withSession(['visitor_registration' => $registration])
            ->get(route('visitor.payment.directpay.status', $payment->reference))
            ->assertRedirect(route('visitor.thank-you'));

        $this->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('REGISTRATION COMPLETE')
            ->assertSee('PARTICIPANT REFERENCE')
            ->assertSee($visitor->verification_id)
            ->assertDontSee('PAYMENT REFERENCE')
            ->assertDontSee($payment->reference)
            ->assertSee('Download Entrance Card')
            ->assertSee('High-quality PNG image');

        $download = $this->get(route('visitor.card.download'));
        $download
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertDownload('payment-visitor-entrance-card.png');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $download->getContent());
    }

    public function test_authenticated_failed_callback_does_not_mark_visitor_paid(): void
    {
        Mail::fake();
        [$visitor, $payment] = $this->pendingPayment();

        $this->sendCallback($this->callbackPayload($payment, 'FAILURE', '5000.00'))
            ->assertOk()
            ->assertJson(['status' => 'failed']);

        $this->assertSame('failed', $visitor->fresh()->payment_status);
        $this->assertNull($visitor->fresh()->paid_at);
        Mail::assertNothingSent();
    }

    public function test_fake_callback_with_invalid_hmac_cannot_mark_visitor_paid(): void
    {
        [$visitor, $payment] = $this->pendingPayment();

        $this->sendCallback($this->callbackPayload($payment, 'SUCCESS', '5000.00'), 'invalid-signature')
            ->assertUnauthorized();

        $this->assertSame('pending', $visitor->fresh()->payment_status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_authenticated_amount_mismatch_is_rejected(): void
    {
        [$visitor, $payment] = $this->pendingPayment();

        $this->sendCallback($this->callbackPayload($payment, 'SUCCESS', '100.00'))
            ->assertUnprocessable();

        $this->assertSame('pending', $visitor->fresh()->payment_status);
    }

    public function test_duplicate_success_callbacks_are_idempotent(): void
    {
        [$visitor, $payment] = $this->pendingPayment();
        $callback = $this->callbackPayload($payment, 'SUCCESS', '5000.00');

        $this->sendCallback($callback)->assertOk();
        $paidAt = $visitor->fresh()->paid_at?->format('Y-m-d H:i:s.u');
        $this->sendCallback($callback)->assertOk();

        $this->assertDatabaseCount('directpay_payments', 1);
        $this->assertSame('paid', $visitor->fresh()->payment_status);
        $this->assertSame($paidAt, $visitor->fresh()->paid_at?->format('Y-m-d H:i:s.u'));
    }

    public function test_already_paid_visitor_cannot_start_another_payment(): void
    {
        $visitor = $this->visitor(['payment_status' => 'paid', 'paid_at' => now()]);

        $this->withSession(['visitor_registration' => $this->registration($visitor)])
            ->post(route('visitor.payment.directpay.start', $visitor), [
                'email' => 'visitor@example.test',
                'mobile' => '+94771234567',
            ])
            ->assertRedirect(route('visitor.thank-you'));

        $this->assertDatabaseCount('directpay_payments', 0);
    }

    public function test_authenticated_unknown_reference_is_rejected_without_modifying_visitors(): void
    {
        $visitor = $this->visitor();
        $payload = $this->callbackPayload((object) ['reference' => 'DP-UNKNOWN'], 'SUCCESS', '5000.00');

        $this->sendCallback($payload)->assertNotFound();

        $this->assertSame('pending', $visitor->fresh()->payment_status);
    }

    private function visitor(array $overrides = []): VerifiedVisitor
    {
        return VerifiedVisitor::create(array_merge([
            'verification_id' => fake()->uuid(),
            'full_name' => 'Payment Visitor',
            'email' => 'visitor@example.test',
            'mobile_number' => '+94771234567',
            'entrance_fee' => 5000,
            'payment_method' => 'visa_master',
            'payment_status' => 'pending',
            'registration_status' => 'payment_pending',
        ], $overrides));
    }

    /** @return array{VerifiedVisitor, DirectPayPayment} */
    private function pendingPayment(): array
    {
        $visitor = $this->visitor();
        $payment = DirectPayPayment::create([
            'verified_visitor_id' => $visitor->id,
            'reference' => 'DP-V'.$visitor->id.'-A82F91',
            'expected_amount' => '5000.00',
            'currency' => 'LKR',
            'status' => 'pending',
        ]);

        return [$visitor, $payment];
    }

    private function registration(VerifiedVisitor $visitor): array
    {
        return [
            'record_id' => $visitor->id,
            'full_name' => $visitor->full_name,
            'payment_method' => 'visa_master',
            'payment_status' => $visitor->payment_status,
        ];
    }

    /** @return array<string, mixed> */
    private function callbackPayload(object $payment, string $status, string $amount): array
    {
        return [
            'type' => 'ONE_TIME',
            'order_id' => $payment->reference,
            'transaction_id' => '880',
            'transaction' => [
                'status' => $status,
                'description' => $status === 'SUCCESS' ? 'Approved' : 'Do not honour',
                'dateTime' => '2026-08-21 10:00:00',
                'amount' => $amount,
                'currency' => 'LKR',
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function sendCallback(array $payload, ?string $signature = null): TestResponse
    {
        $body = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature ??= hash_hmac('sha256', $body, self::SECRET);

        return $this->call('POST', route('directpay.confirmation'), [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_AUTHORIZATION' => 'HMAC '.$signature,
        ], $body);
    }
}
