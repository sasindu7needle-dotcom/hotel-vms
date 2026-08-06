<?php

namespace Tests\Feature;

use App\Models\VerifiedVisitor;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitorRegistrationTest extends TestCase
{
    private array $verification = [
        'session_id' => '11111111-2222-4333-8444-555555555555',
        'full_name' => 'Nimal Perera',
        'document_number' => '199012345678',
        'address' => '12 Galle Road, Colombo',
        'photo_url' => 'https://example.test/verified-photo.jpg',
        'selfie_path' => 'verified-visitors/captured-photo.jpg',
    ];

    private array $category = [
        'name' => 'Adult',
        'entrance_fee' => 1500,
    ];

    public function test_verified_registration_screen_uses_session_identity_and_category(): void
    {
        $this->withSession([
            'verification' => $this->verification,
            'didit_verification' => $this->verification,
            'visitor_category' => $this->category,
        ])->get(route('visitor.create', ['type' => 'passport', 'verified' => 'true']))
            ->assertOk()
            ->assertSee('Nimal Perera')
            ->assertSee('199012345678')
            ->assertSee('12 Galle Road, Colombo')
            ->assertSee('LKR 1,500.00')
            ->assertSee('Same as Mobile')
            ->assertSee('Next');
    }

    public function test_registration_redirects_to_document_upload_when_ocr_fields_are_incomplete(): void
    {
        $incomplete = array_merge($this->verification, [
            'full_name' => '',
            'address' => '',
        ]);

        $this->withSession(['verification' => $incomplete])
            ->get(route('visitor.create', ['type' => 'nic']))
            ->assertRedirect(route('visitor.upload_document', ['type' => 'nic']))
            ->assertSessionHasErrors('verification');
    }

    public function test_confirmation_uses_reviewed_identity_and_server_controlled_fee(): void
    {
        $this->withSession([
            'verification' => $this->verification,
            'didit_verification' => $this->verification,
            'visitor_category' => $this->category,
        ])->post(route('visitor.confirm'), [
            'document_type' => 'passport',
            'mobile_number' => '771234567',
            'same_as_mobile' => '1',
            'occupation' => 'Engineer',
            'company' => 'Acme',
            'full_name' => 'Tampered Name',
            'document_number' => '199012345678',
            'address' => '12 Galle Road, Colombo',
            'entrance_fee' => '0',
        ])->assertOk()
            ->assertSee('Tampered Name')
            ->assertSee('LKR 1,500.00')
            ->assertSee('+94 771234567')
            ->assertSee(route('visitor.session_photo', ['type' => 'selfie']))
            ->assertSee('Choose a payment method');

        $this->assertDatabaseHas('verified_visitors', [
            'verification_id' => $this->verification['session_id'],
            'document_type' => 'passport',
            'document_number' => '199012345678',
            'full_name' => 'Tampered Name',
            'address' => '12 Galle Road, Colombo',
        ]);
    }

    public function test_card_and_cash_methods_route_to_the_correct_next_step(): void
    {
        $registration = [
            'verification_id' => 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
            'full_name' => 'Nimal Perera',
            'entrance_fee' => 1500,
        ];

        try {
            $this->withSession(['visitor_registration' => $registration])
                ->post(route('visitor.payment-method'), ['payment_method' => 'visa_master'])
                ->assertRedirect(route('visitor.payment.card'));

            $this->withSession(['visitor_registration' => $registration])
                ->post(route('visitor.payment-method'), ['payment_method' => 'amex'])
                ->assertRedirect(route('visitor.payment.card'));

            $this->withSession(['visitor_registration' => $registration])
                ->post(route('visitor.payment-method'), ['payment_method' => 'cash'])
                ->assertRedirect(route('visitor.payment.cash'));
        } finally {
            VerifiedVisitor::where('verification_id', $registration['verification_id'])->delete();
        }
    }

    public function test_phone_numbers_must_contain_nine_digits_after_country_prefix(): void
    {
        $this->withSession([
            'verification' => $this->verification,
            'didit_verification' => $this->verification,
            'visitor_category' => $this->category,
        ])->from(route('visitor.create', ['type' => 'nic']))
            ->post(route('visitor.confirm'), [
                'document_type' => 'nic',
                'full_name' => 'Nimal Perera',
                'document_number' => '199012345678',
                'address' => '12 Galle Road, Colombo',
                'mobile_number' => '123',
                'whatsapp_number' => '456',
                'occupation' => 'Engineer',
                'company' => 'Acme',
            ])->assertRedirect()
            ->assertSessionHasErrors(['mobile_number', 'whatsapp_number']);
    }

    public function test_payment_confirmation_displays_the_server_generated_visitor_badge(): void
    {
        $visitor = VerifiedVisitor::updateOrCreate(
            ['verification_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'],
            array_merge([
                'full_name' => 'Nimal Perera',
                'category' => 'Adult',
                'payment_method' => 'visa_master',
                 'payment_status' => 'pending',
                'registration_status' => 'payment_pending',
            ], Schema::hasColumn('verified_visitors', 'didit_session_id') ? [
                'didit_session_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            ] : [])
        );

        try {
            $registration = [
                'record_id' => $visitor->id,
                'full_name' => 'Nimal Perera',
                'category' => 'Adult',
                'payment_method' => 'visa_master',
            ];

            $this->withSession(['visitor_registration' => $registration])
                ->post(route('visitor.payment.confirm'))
                ->assertRedirect(route('visitor.thank-you'));

            $this->get(route('visitor.thank-you'))
                ->assertOk()
                ->assertSee('Thank you for registering')
                ->assertSee('Nimal Perera')
                ->assertSee('Adult')
                ->assertSee('Printing Booth')
                ->assertSee('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee')
                ->assertSee('<svg', false);

            $this->assertDatabaseHas('verified_visitors', [
                'id' => $visitor->id,
                'payment_status' => 'paid',
                'registration_status' => 'registered',
            ]);
        } finally {
            $visitor->delete();
        }
    }
}
