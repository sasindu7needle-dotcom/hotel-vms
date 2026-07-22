<?php

namespace Tests\Feature;

use Tests\TestCase;

class VisitorRegistrationTest extends TestCase
{
    private array $verification = [
        'session_id' => '11111111-2222-4333-8444-555555555555',
        'full_name' => 'Nimal Perera',
        'document_number' => '199012345678',
        'address' => '12 Galle Road, Colombo',
        'photo_url' => 'https://example.test/verified-photo.jpg',
        'face_verification_status' => 'verified',
        'face_match_score' => 88.5,
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
            ->assertSee('https://example.test/verified-photo.jpg')
            ->assertSee('Do you want to make the payment by Card or Cash?');

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
            'full_name' => 'Nimal Perera',
            'entrance_fee' => 1500,
        ];

        $this->withSession(['visitor_registration' => $registration])
            ->post(route('visitor.payment-method'), ['payment_method' => 'visa_master'])
            ->assertRedirect(route('visitor.payment.card'));

        $this->withSession(['visitor_registration' => $registration])
            ->post(route('visitor.payment-method'), ['payment_method' => 'amex'])
            ->assertRedirect(route('visitor.payment.card'));

        $this->withSession(['visitor_registration' => $registration])
            ->post(route('visitor.payment-method'), ['payment_method' => 'cash'])
            ->assertRedirect(route('visitor.payment.cash'));
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
}
