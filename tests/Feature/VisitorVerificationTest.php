<?php

namespace Tests\Feature;

use App\Http\Controllers\VisitorCheckinController;
use App\Services\GeminiDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VisitorVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('services.google_vision.api_key', 'test-vision-api-key');
        config()->set('services.google_translate.api_key', 'test-translation-api-key');
    }

    public function test_it_verifies_document_with_gemini_and_prefills_complete_identity(): void
    {
        $this->mockGemini([
            'document_number' => '199012345678',
            'full_name' => 'Nimal Kamal Perera',
            'address' => 'No. 12, Galle Road, Colombo 03',
        ]);

        $file = UploadedFile::fake()->image('nic.jpg', 600, 400);

        $response = $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => $file,
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 600, 400),
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.document_number', '199012345678')
            ->assertJsonPath('data.full_name', 'Nimal Kamal Perera')
            ->assertJsonPath('data.address', 'No. 12, Galle Road, Colombo 03')
            ->assertJsonPath('data.provider', 'google_gemini');

        $this->assertNotNull(session('verification'));
        $this->assertEquals('199012345678', session('verification.document_number'));
        $this->assertEquals('pending', session('verification.photo_capture_status'));
        $response->assertJsonPath('redirect_url', route('visitor.photo_capture'));
    }

    public function test_it_rejects_invalid_document_type_or_missing_image(): void
    {
        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'invalid_type',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type', 'document_front_image']);
    }

    public function test_gemini_service_joins_every_wrapped_name_line_from_the_back(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiApiResponse([
                    'document_number' => '200124103810',
                    'full_name_lines' => ['Senarath Wickramanayaka Mudiyanselage Bimasara'],
                    'full_name' => 'Senarath Wickramanayaka Mudiyanselage Bimasara',
                    'address_lines' => ['88, Harigala, Atala'],
                    'address' => '88, Harigala, Atala',
                    'full_name_original' => '',
                    'address_original' => '',
                ]))
                ->push($this->geminiApiResponse([
                    'document_number' => '',
                    'full_name_lines' => [
                        'Senarath Wickramanayaka Mudiyanselage Bimasara',
                        'Nisal Wickramanayaka',
                    ],
                    'full_name' => 'Senarath Wickramanayaka Mudiyanselage Bimasara Nisal Wickramanayaka',
                    'address_lines' => ['88, Harigala, Atala'],
                    'address' => '88, Harigala, Atala',
                    'full_name_original' => '',
                    'address_original' => '',
                ])),
        ]);

        $front = UploadedFile::fake()->image('front.jpg', 600, 400);
        $back = UploadedFile::fake()->image('back.jpg', 600, 400);
        $result = app(GeminiDocumentService::class)->extract(
            $front->getRealPath(),
            'image/jpeg',
            $back->getRealPath(),
            'image/jpeg'
        );

        $this->assertSame(
            'Senarath Wickramanayaka Mudiyanselage Bimasara Nisal Wickramanayaka',
            $result['full_name']
        );
    }

    public function test_it_does_not_continue_when_name_or_address_is_missing(): void
    {
        $this->mockGemini(['document_number' => '200124103810']);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 800, 500),
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'incomplete_identity_fields');

        $this->assertNull(session('verification'));
    }

    public function test_it_extracts_multiline_identity_fields_for_all_document_types(): void
    {
        $parser = new \ReflectionMethod(VisitorCheckinController::class, 'parseDocumentText');
        $controller = app(VisitorCheckinController::class);
        $samples = [
            'nic' => [
                "199012345678\nFULL NAME\nNIMAL PERERA\nADDRESS\nNO. 12\nGALLE ROAD\nCOLOMBO\nDATE OF BIRTH\n1990-01-01",
                ['199012345678', 'NIMAL PERERA', 'NO. 12, GALLE ROAD, COLOMBO'],
            ],
            'driving_license' => [
                "LICENCE NO\nB1234567\nSURNAME\nPERERA\nOTHER NAMES\nNIMAL KAMAL\nPERMANENT PLACE OF RESIDENCE\nNO. 8\nKANDY ROAD\nKANDY\nDATE OF ISSUE\n2025-01-01",
                ['B1234567', 'NIMAL KAMAL PERERA', 'NO. 8, KANDY ROAD, KANDY'],
            ],
            'passport' => [
                "PASSPORT NO: N1234567\nSURNAME\nPERERA\nGIVEN NAMES\nNIMAL KAMAL\nADDRESS\n45 GALLE ROAD\nCOLOMBO",
                ['N1234567', 'NIMAL KAMAL PERERA', '45 GALLE ROAD, COLOMBO'],
            ],
        ];

        foreach ($samples as $type => [$ocrText, $expected]) {
            $parsed = $parser->invoke($controller, $ocrText, $type);
            $this->assertSame($expected[0], $parsed['document_number']);
            $this->assertSame($expected[1], $parsed['full_name']);
            $this->assertSame($expected[2], $parsed['address']);
        }
    }

    public function test_it_translates_sinhala_name_and_address_to_english(): void
    {
        $this->mockGemini([
            'document_number' => '199012345678',
            'full_name' => 'Nimal Perera',
            'address' => 'No. 12, Galle Road, Colombo',
            'full_name_original' => 'නිමල් පෙරේරා',
            'address_original' => 'අංක 12, ගාලු පාර, කොළඹ',
        ]);

        Http::fake([
            'translate.googleapis.com/*' => Http::sequence()
                ->push([[['Nimal Perera']]])
                ->push([[['No. 12, Galle Road, Colombo']]]),
        ]);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 800, 500),
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Nimal Perera')
            ->assertJsonPath('data.address', 'No. 12, Galle Road, Colombo')
            ->assertJsonPath('data.full_name_original', 'නිමල් පෙරේරා')
            ->assertJsonPath('data.address_original', 'අංක 12, ගාලු පාර, කොළඹ');
    }

    public function test_it_translates_tamil_name_and_address_to_english(): void
    {
        $this->mockGemini([
            'document_number' => 'N1234567',
            'full_name' => 'Kumar Siva',
            'address' => 'No. 8, Galle Road, Colombo',
            'full_name_original' => 'குமார் சிவா',
            'address_original' => 'இல 8, காலி வீதி, கொழும்பு',
        ]);

        Http::fake([
            'translate.googleapis.com/*' => Http::sequence()
                ->push([[['Kumar Siva']]])
                ->push([[['No. 8, Galle Road, Colombo']]]),
        ]);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'passport',
            'document_front_image' => UploadedFile::fake()->image('passport.jpg', 800, 500),
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Kumar Siva')
            ->assertJsonPath('data.address', 'No. 8, Galle Road, Colombo')
            ->assertJsonPath('data.full_name_original', 'குமார் சிவா')
            ->assertJsonPath('data.address_original', 'இல 8, காலி வீதி, கொழும்பு');
    }

    public function test_it_verifies_a_driving_license_without_a_back_image(): void
    {
        $this->mockGemini([
            'document_number' => 'B1234567',
            'nic_number' => '200109402239',
            'driving_license_number' => 'B1234567',
            'full_name' => 'Nimal Perera',
            'address' => '12 Galle Road, Colombo',
        ]);

        $response = $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => UploadedFile::fake()->image('license-front.jpg', 600, 400),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.document_type', 'driving_license')
            ->assertJsonPath('data.document_number', '200109402239')
            ->assertJsonPath('data.full_name', 'Nimal Perera')
            ->assertJsonPath('data.back_photo_path', null);

    }

    public function test_it_verifies_a_passport_identity_page_without_a_back_image(): void
    {
        $this->mockGemini([
            'document_number' => 'N1234567',
            'full_name' => 'Nimal Perera',
            'address' => '45 Galle Road, Colombo',
        ]);

        $response = $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'passport',
            'document_front_image' => UploadedFile::fake()->image('passport-identity-page.jpg', 600, 400),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.document_type', 'passport')
            ->assertJsonPath('data.document_number', 'N1234567')
            ->assertJsonPath('data.full_name', 'Nimal Perera')
            ->assertJsonPath('data.back_photo_path', null);

    }

    public function test_only_nic_requires_a_back_image(): void
    {
        $this->mockGemini([]);

        Http::fake([
            'vision.googleapis.com/*' => Http::response(['responses' => [[]]]),
        ]);

        $front = fn (string $name) => UploadedFile::fake()->image($name, 600, 400);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => $front('nic-front.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('document_back_image');

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => $front('license-front.jpg'),
        ])->assertJsonMissingValidationErrors('document_back_image');

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'passport',
            'document_front_image' => $front('passport-page.jpg'),
        ])->assertJsonMissingValidationErrors('document_back_image');
    }

    public function test_driving_license_and_passport_upload_pages_use_one_document_side(): void
    {
        foreach (['driving_license', 'passport'] as $documentType) {
            $response = $this->get(route('visitor.upload_document', ['type' => $documentType]))->assertOk();

            $this->assertMatchesRegularExpression('/class="document-sides\s+document-sides--single\s*"/', $response->getContent());
            $this->assertMatchesRegularExpression('/class="document-side\s+document-side--hidden\s*" id="backDocumentSide"/', $response->getContent());
        }

        $nicResponse = $this->get(route('visitor.upload_document', ['type' => 'nic']))->assertOk();
        $this->assertDoesNotMatchRegularExpression('/class="document-sides\s+document-sides--single\s*"/', $nicResponse->getContent());
        $this->assertDoesNotMatchRegularExpression('/class="document-side\s+document-side--hidden\s*" id="backDocumentSide"/', $nicResponse->getContent());
    }

    public function test_it_rejects_a_document_when_gemini_cannot_read_it(): void
    {
        $this->mockGemini([]);

        $file = UploadedFile::fake()->image('license.png', 500, 300);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => $file,
            'document_back_image' => UploadedFile::fake()->image('license-back.png', 500, 300),
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_stores_a_camera_photo_and_unlocks_registration(): void
    {
        Storage::disk('local')->put('verified-visitors/document.jpg', 'document');
        $response = $this->withSession(['verification' => [
            'session_id' => '11111111-2222-4333-8444-555555555555',
            'verification_id' => '11111111-2222-4333-8444-555555555555',
            'document_type' => 'nic',
            'photo_path' => 'verified-visitors/document.jpg',
        ]])->postJson(route('visitor.capture_photo'), [
            'selfie' => UploadedFile::fake()->image('camera-photo.jpg', 1280, 960),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('completed', session('verification.photo_capture_status'));
        Storage::disk('local')->assertExists('verified-visitors/11111111-2222-4333-8444-555555555555-photo.jpg');
    }

    public function test_it_rejects_an_invalid_camera_photo(): void
    {
        $response = $this->withSession(['verification' => [
            'session_id' => '11111111-2222-4333-8444-555555555555',
            'verification_id' => '11111111-2222-4333-8444-555555555555',
            'document_type' => 'nic',
            'photo_path' => 'verified-visitors/document.jpg',
        ]])->postJson(route('visitor.capture_photo'), [
            'selfie' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('selfie');
        $this->assertNull(session('verification.selfie_path'));
    }

    private function mockGemini(array $result): void
    {
        $this->mock(GeminiDocumentService::class, function ($mock) use ($result) {
            $mock->shouldReceive('extract')->andReturn(array_merge([
                'document_number' => '',
                'nic_number' => '',
                'driving_license_number' => '',
                'full_name' => '',
                'address' => '',
                'full_name_original' => '',
                'address_original' => '',
            ], $result));
        });
    }

    private function geminiApiResponse(array $result): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ]],
                ],
            ]],
        ];
    }
}
