<?php

namespace Tests\Feature;

use App\Models\VerifiedVisitor;
use App\Services\GeminiDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NicRegistrationResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_an_unpaid_registered_nic_resumes_at_payment(): void
    {
        Storage::fake('visitor-media');
        $visitor = $this->visitor(['payment_status' => 'pending']);
        $this->mockNicReader(['full_name' => '', 'address' => '']);

        $response = $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 600, 400),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 600, 400),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resumed_registration', true)
            ->assertJsonPath('redirect_url', route('visitor.payment.card'));
        $this->assertSame($visitor->id, session('visitor_registration.record_id'));
        $this->assertSame('visa_master', session('visitor_registration.payment_method'));
        $this->assertSame('pending', session('visitor_registration.payment_status'));
        $this->assertNull(session('verification'));
        $this->assertSame('visa_master', $visitor->fresh()->payment_method);
        $this->assertDatabaseCount('verified_visitors', 1);

        $this->get(route('visitor.payment.card'))
            ->assertOk()
            ->assertSee('LKR 7,500.00')
            ->assertSee('Your existing unpaid registration was found');
    }

    public function test_retrying_a_failed_historical_nic_payment_redirects_to_payment(): void
    {
        $visitor = $this->visitor([
            'document_number' => '1990 1234-5678',
            'nic_registration_key' => null,
            'payment_method' => 'visa_master',
            'payment_status' => 'failed',
            'registration_status' => 'payment_failed',
        ]);
        $this->mockNicReader(['full_name' => '', 'address' => '']);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 600, 400),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 600, 400),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resumed_registration', true)
            ->assertJsonPath('redirect_url', route('visitor.payment.card'));

        $this->assertSame($visitor->id, session('visitor_registration.record_id'));
        $this->assertSame('pending', session('visitor_registration.payment_status'));
        $this->assertDatabaseHas('verified_visitors', [
            'id' => $visitor->id,
            'payment_method' => 'visa_master',
            'payment_status' => 'pending',
            'registration_status' => 'payment_pending',
        ]);

        $this->get(route('visitor.payment.card'))->assertOk();
    }

    public function test_uploading_a_driving_license_resumes_the_unpaid_record_by_field_4c_nic(): void
    {
        $visitor = $this->visitor([
            'document_type' => 'driving_license',
            'document_number' => '993100900V',
            'nic_registration_key' => null,
            'payment_status' => 'failed',
        ]);
        $this->mockDrivingLicenseReader();

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => UploadedFile::fake()->image('licence-front.jpg', 600, 400),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resumed_registration', true)
            ->assertJsonPath('redirect_url', route('visitor.payment.card'));

        $this->assertSame($visitor->id, session('visitor_registration.record_id'));
        $this->assertSame('pending', session('visitor_registration.payment_status'));
        $this->get(route('visitor.payment.card'))->assertOk();
    }

    public function test_uploading_a_passport_resumes_the_unpaid_record_by_passport_number(): void
    {
        $visitor = $this->visitor([
            'document_type' => 'passport',
            'document_number' => 'N1234567',
            'nic_registration_key' => null,
            'passport_registration_key' => 'N1234567',
            'payment_status' => 'failed',
        ]);
        $this->mockPassportReader();

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'passport',
            'document_front_image' => UploadedFile::fake()->image('passport.jpg', 600, 400),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resumed_registration', true)
            ->assertJsonPath('redirect_url', route('visitor.payment.card'));

        $this->assertSame($visitor->id, session('visitor_registration.record_id'));
        $this->assertSame('pending', session('visitor_registration.payment_status'));
        $this->get(route('visitor.payment.card'))->assertOk();
    }

    public function test_uploading_an_already_paid_nic_opens_the_thank_you_card_page(): void
    {
        $visitor = $this->visitor([
            'full_name' => 'Existing Paid Visitor',
            'payment_method' => 'visa_master',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'registration_status' => 'registered',
        ]);
        $this->mockNicReader(['full_name' => '', 'address' => '']);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 600, 400),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 600, 400),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resumed_registration', true)
            ->assertJsonPath('paid_registration', true)
            ->assertJsonPath('redirect_url', route('visitor.thank-you'));

        $this->assertSame($visitor->id, session('visitor_registration.record_id'));
        $this->assertSame('paid', session('visitor_registration.payment_status'));
        $this->assertNull(session('verification'));
        $this->assertSame('paid', $visitor->fresh()->payment_status);
        $this->assertDatabaseCount('verified_visitors', 1);

        $this->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('REGISTRATION COMPLETE')
            ->assertSee('Download Entrance Card');

        $this->get(route('visitor.card.download'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertDownload('existing-paid-visitor-entrance-card.png');
    }

    public function test_paid_historical_duplicate_takes_priority_and_opens_the_card_page(): void
    {
        $this->visitor([
            'verification_id' => '11111111-2222-4333-8444-555555555555',
            'payment_status' => 'failed',
        ]);
        $paidVisitor = $this->visitor([
            'verification_id' => '99999999-8888-4777-8666-555555555555',
            'document_number' => '1990-1234-5678',
            'nic_registration_key' => null,
            'full_name' => 'Historical Paid Visitor',
            'payment_method' => 'visa_master',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'registration_status' => 'registered',
        ]);
        $this->mockNicReader(['full_name' => '', 'address' => '']);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 600, 400),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 600, 400),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('paid_registration', true)
            ->assertJsonPath('redirect_url', route('visitor.thank-you'));

        $this->assertSame($paidVisitor->id, session('visitor_registration.record_id'));
        $this->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('Download Entrance Card');
    }

    public function test_uploading_a_driving_license_opens_the_paid_records_card_by_field_4c_nic(): void
    {
        $visitor = $this->visitor([
            'document_type' => 'driving_license',
            'document_number' => '993100900V',
            'nic_registration_key' => null,
            'full_name' => 'Paid Driving Licence Visitor',
            'payment_method' => 'visa_master',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'registration_status' => 'registered',
        ]);
        $this->mockDrivingLicenseReader();

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => UploadedFile::fake()->image('licence-front.jpg', 600, 400),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('paid_registration', true)
            ->assertJsonPath('redirect_url', route('visitor.thank-you'));

        $this->assertSame($visitor->id, session('visitor_registration.record_id'));
        $this->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('Download Entrance Card');
    }

    public function test_uploading_a_passport_opens_the_paid_records_card_by_passport_number(): void
    {
        $visitor = $this->visitor([
            'document_type' => 'passport',
            'document_number' => 'N1234567',
            'nic_registration_key' => null,
            'passport_registration_key' => 'N1234567',
            'full_name' => 'Paid Passport Visitor',
            'payment_method' => 'visa_master',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'registration_status' => 'registered',
        ]);
        $this->mockPassportReader();

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'passport',
            'document_front_image' => UploadedFile::fake()->image('passport.jpg', 600, 400),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('paid_registration', true)
            ->assertJsonPath('redirect_url', route('visitor.thank-you'));

        $this->assertSame($visitor->id, session('visitor_registration.record_id'));
        $this->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('Download Entrance Card');
    }

    private function visitor(array $overrides = []): VerifiedVisitor
    {
        return VerifiedVisitor::create(array_merge([
            'verification_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            'document_type' => 'nic',
            'document_number' => '199012345678',
            'nic_registration_key' => '199012345678',
            'full_name' => 'Existing Unpaid Visitor',
            'email' => 'existing@example.test',
            'mobile_number' => '+94771234567',
            'category' => 'Participant',
            'entrance_fee' => 7500,
            'payment_method' => null,
            'payment_status' => 'pending',
            'registration_status' => 'payment_pending',
        ], $overrides));
    }

    private function mockNicReader(array $overrides = []): void
    {
        $this->mock(GeminiDocumentService::class, function ($mock) use ($overrides) {
            $mock->shouldReceive('extract')->once()->andReturn(array_merge([
                'document_number' => '1990 1234-5678',
                'full_name' => 'Existing Unpaid Visitor',
                'address' => '12 Galle Road, Colombo',
            ], $overrides));
        });
    }

    private function mockDrivingLicenseReader(): void
    {
        $this->mock(GeminiDocumentService::class, function ($mock) {
            $mock->shouldReceive('extract')->once()->andReturn([
                'document_number' => 'B4378596',
                'nic_number' => '993100900V',
                'driving_license_number' => 'B4378596',
                'full_name' => '',
                'address' => '',
            ]);
        });
    }

    private function mockPassportReader(): void
    {
        $this->mock(GeminiDocumentService::class, function ($mock) {
            $mock->shouldReceive('extract')->once()->andReturn([
                'document_number' => 'N 123-4567',
                'full_name' => '',
                'address' => '',
            ]);
        });
    }
}
