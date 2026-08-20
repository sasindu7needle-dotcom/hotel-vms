<?php

namespace Tests\Feature;

use App\Models\DirectPayPayment;
use App\Models\VerifiedVisitor;
use App\Services\DirectPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DirectPayPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.directpay', [
            'environment' => 'sandbox',
            'merchant_id' => 'TEST01',
            'api_key' => 'sandbox-api-key',
            'merchant_secret' => 'sandbox-private-key-placeholder',
            'private_key_path' => null,
            'currency' => 'LKR',
            'status_url' => 'https://dev.directpay.lk/v1/mpg/api/external/transaction/paymentStatus',
            'timeout' => 15,
        ]);
    }

    public function test_payment_start_uses_database_amount_and_ignores_browser_amount(): void
    {
        $visitor = $this->visitor(['entrance_fee' => 5000]);

        $this->withSession(['visitor_registration' => $this->registration($visitor)])
            ->post(route('visitor.payment.directpay.start', $visitor), [
                'email' => 'visitor@example.test',
                'amount' => '1.00',
            ])
            ->assertRedirect();

        $payment = DirectPayPayment::firstOrFail();
        $this->assertSame('5000.00', $payment->expected_amount);
        $this->assertSame('LKR', $payment->currency);
        $this->assertSame('pending', $payment->status);
        $this->assertMatchesRegularExpression('/^DP-V\d+-[A-Z0-9]+$/', $payment->reference);
        $this->assertLessThanOrEqual(20, strlen($payment->reference));

        $this->get(route('visitor.payment.directpay.checkout', $payment->reference))
            ->assertOk()
            ->assertSee('https://cdn.directpay.lk/dev/v1/directpayCardPayment.js?v=1', false)
            ->assertSee('ONE_TIME_PAYMENT')
            ->assertSee('5000.00')
            ->assertSee($payment->reference)
            ->assertDontSee('sandbox-private-key-placeholder');
    }

    public function test_verified_success_callback_marks_payment_and_visitor_paid(): void
    {
        [$visitor, $payment] = $this->pendingPayment();
        $this->mockVerification('SUCCESS', '5000.00');

        $this->postJson(route('directpay.confirmation'), $this->callbackPayload($payment, 'SUCCESS', '5000.00'))
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

    public function test_verified_failed_callback_does_not_mark_visitor_paid(): void
    {
        [$visitor, $payment] = $this->pendingPayment();
        $this->mockVerification('FAILED', '5000.00');

        $this->postJson(route('directpay.confirmation'), $this->callbackPayload($payment, 'FAILED', '5000.00'))
            ->assertOk()
            ->assertJson(['status' => 'failed']);

        $this->assertSame('failed', $visitor->fresh()->payment_status);
        $this->assertNull($visitor->fresh()->paid_at);
    }

    public function test_unverified_fake_callback_cannot_mark_visitor_paid(): void
    {
        [$visitor, $payment] = $this->pendingPayment();
        $this->partialMock(DirectPayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->once()->andThrow(new RuntimeException('Not verified.'));
        });

        $this->postJson(route('directpay.confirmation'), $this->callbackPayload($payment, 'SUCCESS', '5000.00'))
            ->assertStatus(503);

        $this->assertSame('pending', $visitor->fresh()->payment_status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        [$visitor, $payment] = $this->pendingPayment();
        $this->mockVerification('SUCCESS', '100.00');

        $this->postJson(route('directpay.confirmation'), $this->callbackPayload($payment, 'SUCCESS', '5000.00'))
            ->assertStatus(422);

        $this->assertSame('pending', $visitor->fresh()->payment_status);
    }

    public function test_duplicate_success_callbacks_are_idempotent(): void
    {
        [$visitor, $payment] = $this->pendingPayment();
        $this->mockVerification('SUCCESS', '5000.00', 2);
        $callback = $this->callbackPayload($payment, 'SUCCESS', '5000.00');

        $this->postJson(route('directpay.confirmation'), $callback)->assertOk();
        $paidAt = $visitor->fresh()->paid_at?->format('Y-m-d H:i:s.u');
        $this->postJson(route('directpay.confirmation'), $callback)->assertOk();

        $this->assertDatabaseCount('directpay_payments', 1);
        $this->assertSame('paid', $visitor->fresh()->payment_status);
        $this->assertSame($paidAt, $visitor->fresh()->paid_at?->format('Y-m-d H:i:s.u'));
    }

    public function test_already_paid_visitor_cannot_start_another_payment(): void
    {
        $visitor = $this->visitor(['payment_status' => 'paid', 'paid_at' => now()]);

        $this->withSession(['visitor_registration' => $this->registration($visitor)])
            ->post(route('visitor.payment.directpay.start', $visitor), ['email' => 'visitor@example.test'])
            ->assertRedirect(route('visitor.thank-you'));

        $this->assertDatabaseCount('directpay_payments', 0);
    }

    public function test_unknown_reference_is_rejected_without_modifying_visitors(): void
    {
        $visitor = $this->visitor();
        $payload = $this->callbackPayload((object) ['reference' => 'DP-UNKNOWN'], 'SUCCESS', '5000.00');

        $this->postJson(route('directpay.confirmation'), $payload)->assertNotFound();

        $this->assertSame('pending', $visitor->fresh()->payment_status);
    }

    private function visitor(array $overrides = []): VerifiedVisitor
    {
        return VerifiedVisitor::create(array_merge([
            'verification_id' => fake()->uuid(),
            'full_name' => 'Payment Visitor',
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

    private function callbackPayload(object $payment, string $status, string $amount): array
    {
        return [
            'status' => 200,
            'type' => 'INIT_TRN',
            'paymentCategory' => 'ONE_TIME',
            'data' => [
                'transactionId' => 880,
                'status' => $status,
                'description' => $status === 'SUCCESS' ? 'Approved' : 'Do not honour',
                'dateTime' => '2026-08-21 10:00:00',
                'reference' => $payment->reference,
                'amount' => $amount,
                'currency' => 'LKR',
            ],
        ];
    }

    private function mockVerification(string $status, string $amount, int $times = 1): void
    {
        $this->partialMock(DirectPayService::class, function ($mock) use ($status, $amount, $times) {
            $mock->shouldReceive('verifyTransaction')->times($times)->andReturn([
                'transactionId' => 880,
                'status' => $status,
                'bankerResponseDesc' => $status === 'SUCCESS' ? null : 'Do not honour',
                'amount' => $amount,
                'currency' => 'LKR',
                'dateTime' => '2026-08-21 10:00:00',
            ]);
        });
    }
}
