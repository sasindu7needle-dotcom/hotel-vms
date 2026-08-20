<?php

namespace Tests\Feature;

use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VisitorRegistrationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_passport_registration_accepts_an_empty_extracted_address_for_manual_entry(): void
    {
        $passportVerification = array_merge($this->verification, [
            'document_type' => 'passport',
            'document_number' => 'N1234567',
            'address' => '',
        ]);

        $this->withSession([
            'verification' => $passportVerification,
            'visitor_category' => $this->category,
        ])->get(route('visitor.create', ['type' => 'passport']))
            ->assertOk()
            ->assertSee('N1234567')
            ->assertSee('Your passport does not show a residential address. Please enter your current address.');
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
        ])->assertRedirect(route('visitor.confirm.show'));

        $this->get(route('visitor.confirm.show'))
            ->assertOk()
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

    public function test_confirmation_get_requires_an_active_registration_session(): void
    {
        $this->get(route('visitor.confirm.show'))
            ->assertRedirect(route('visitor.create'))
            ->assertSessionHasErrors('registration');
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
                ->from(route('visitor.confirm.show'))
                ->post(route('visitor.payment-method'), ['payment_method' => 'amex'])
                ->assertRedirect(route('visitor.confirm.show'))
                ->assertSessionHasErrors('payment_method');

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

    public function test_nic_name_must_be_confirmed_before_registration(): void
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
                'mobile_number' => '771234567',
                'whatsapp_number' => '771234567',
                'occupation' => 'Engineer',
                'company' => 'Acme',
            ])->assertRedirect()
            ->assertSessionHasErrors('name_confirmation');
    }

    public function test_browser_payment_confirmation_cannot_mark_a_visitor_paid(): void
    {
        $visitor = VerifiedVisitor::updateOrCreate(
            ['verification_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'],
            array_merge([
                'full_name' => 'Nimal Perera',
                'category' => 'Adult',
                'occupation' => 'Hotel Manager',
                'company' => 'Harbour Hotels',
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
                'occupation' => 'Hotel Manager',
                'company' => 'Harbour Hotels',
                'payment_method' => 'visa_master',
            ];

            $this->withSession(['visitor_registration' => $registration])
                ->post(route('visitor.payment.confirm'))
                ->assertRedirect(route('visitor.payment.card'));

            $this->assertDatabaseHas('verified_visitors', [
                'id' => $visitor->id,
                'payment_status' => 'pending',
                'registration_status' => 'payment_pending',
            ]);
        } finally {
            $visitor->delete();
        }
    }

    public function test_cash_payment_screen_offers_a_session_scoped_card_download(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verified-visitors/cash-visitor.jpg', 'visitor-photo');
        $visitor = VerifiedVisitor::create([
            'verification_id' => 'cccccccc-dddd-4eee-8fff-aaaaaaaaaaaa',
            'full_name' => 'Cash Visitor',
            'category' => 'Adult',
            'occupation' => 'Concierge',
            'company' => 'City Hotel',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'registration_status' => 'payment_pending',
            'selfie_path' => 'verified-visitors/cash-visitor.jpg',
            'selfie_mime' => 'image/jpeg',
        ]);
        $registration = [
            'record_id' => $visitor->id,
            'full_name' => $visitor->full_name,
            'payment_method' => 'cash',
        ];

        $this->withSession(['visitor_registration' => $registration])
            ->get(route('visitor.payment.cash'))
            ->assertOk()
            ->assertSee('Download Entrance Card')
            ->assertSee(route('visitor.card.download'));

        $download = $this->get(route('visitor.card.download'));
        $download
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertDownload('cash-visitor-entrance-card.svg')
            ->assertSee('PAYMENT PENDING')
            ->assertSee('Cash Visitor')
            ->assertSee('OCCUPATION')
            ->assertSee('Concierge')
            ->assertSee('COMPANY')
            ->assertSee('City Hotel')
            ->assertDontSee('EVENT NAME')
            ->assertSee('data:image/png;base64,', false)
            ->assertSee($visitor->verification_id)
            ->assertSee('data:image/jpeg;base64,', false);

        $svg = new \DOMDocument();
        $this->assertTrue($svg->loadXML($download->getContent()), 'The downloaded card must be valid SVG/XML.');
    }

    public function test_card_download_requires_the_active_registration_session(): void
    {
        $this->get(route('visitor.card.download'))
            ->assertRedirect(route('visitor.create'));
    }
}
