<?php

namespace Tests\Feature;

use App\Services\LocalFaceVerificationService;
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
        $this->mock(LocalFaceVerificationService::class, function ($mock) {
            $mock->shouldReceive('inspectDocument')->zeroOrMoreTimes()->andReturn([
                'success' => true,
                'face_detected' => true,
                'detection_confidence' => 96.0,
            ]);
            $mock->shouldReceive('compare')->zeroOrMoreTimes()->andReturn([
                'success' => true,
                'matched' => true,
                'similarity_percent' => 78.5,
                'live_detection_confidence' => 97.0,
                'message' => 'The live face matches the identity-document portrait.',
            ]);
        });
    }

    public function test_it_verifies_document_with_google_cloud_vision(): void
    {
        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'responses' => [
                    [
                        'fullTextAnnotation' => [
                            'text' => "SRI LANKA NIC\n199012345678\nNAME: Nimal Perera\nADDRESS: 12 Galle Road, Colombo",
                        ],
                        'faceAnnotations' => [$this->faceAnnotation()],
                    ],
                    ['fullTextAnnotation' => ['text' => 'Back side details']],
                ],
            ]),
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
            ->assertJsonPath('data.full_name', 'Nimal Perera')
            ->assertJsonPath('data.address', '12 Galle Road, Colombo');

        $this->assertNotNull(session('verification'));
        $this->assertEquals('199012345678', session('verification.document_number'));
        $this->assertEquals('pending', session('verification.face_verification_status'));
        $response->assertJsonPath('redirect_url', route('visitor.live_face'));
    }

    public function test_it_rejects_invalid_document_type_or_missing_image(): void
    {
        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'invalid_type',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type', 'document_front_image']);
    }

    public function test_it_verifies_a_driving_license_without_a_back_image(): void
    {
        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "DRIVING LICENCE\nNO: B1234567\nNAME: Nimal Perera\nADDRESS: 12 Galle Road, Colombo",
                    ],
                    'faceAnnotations' => [$this->faceAnnotation()],
                ]],
            ]),
        ]);

        $response = $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => UploadedFile::fake()->image('license-front.jpg', 600, 400),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.document_type', 'driving_license')
            ->assertJsonPath('data.document_number', 'B1234567')
            ->assertJsonPath('data.full_name', 'Nimal Perera')
            ->assertJsonPath('data.back_photo_path', null);

        Http::assertSentCount(1);
    }

    public function test_it_verifies_a_passport_identity_page_without_a_back_image(): void
    {
        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "SRI LANKA PASSPORT\nPASSPORT NO: N1234567\nNAME: Nimal Perera\nN1234567<7LKA9001011M3001012",
                    ],
                    'faceAnnotations' => [$this->faceAnnotation()],
                ]],
            ]),
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

        Http::assertSentCount(1);
    }

    public function test_only_nic_requires_a_back_image(): void
    {
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

    public function test_it_rejects_a_document_when_all_ocr_providers_fail(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Vision API offline'));

        $file = UploadedFile::fake()->image('license.png', 500, 300);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => $file,
            'document_back_image' => UploadedFile::fake()->image('license-back.png', 500, 300),
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_verifies_a_live_camera_face_and_unlocks_registration(): void
    {
        Storage::disk('local')->put('verified-visitors/document.jpg', 'document');
        $response = $this->withSession(['verification' => [
            'session_id' => '11111111-2222-4333-8444-555555555555',
            'verification_id' => '11111111-2222-4333-8444-555555555555',
            'document_type' => 'nic',
            'photo_path' => 'verified-visitors/document.jpg',
        ]])->postJson(route('visitor.verify_live_face'), [
            'selfie' => UploadedFile::fake()->image('live-camera.jpg', 1280, 960),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('verified', session('verification.face_verification_status'));
        $this->assertEquals('opencv_yunet_sface', session('verification.face_provider'));
        Storage::disk('local')->assertExists('verified-visitors/11111111-2222-4333-8444-555555555555-live.jpg');
    }

    public function test_it_does_not_unlock_registration_when_faces_do_not_match(): void
    {
        $this->mock(LocalFaceVerificationService::class, function ($mock) {
            $mock->shouldReceive('compare')->once()->andReturn([
                'success' => true,
                'matched' => false,
                'similarity_percent' => 12.4,
                'live_detection_confidence' => 96.0,
                'message' => 'The live face does not sufficiently match the identity-document portrait.',
            ]);
        });

        Storage::disk('local')->put('verified-visitors/document.jpg', 'document');
        $response = $this->withSession(['verification' => [
            'session_id' => '11111111-2222-4333-8444-555555555555',
            'verification_id' => '11111111-2222-4333-8444-555555555555',
            'document_type' => 'nic',
            'photo_path' => 'verified-visitors/document.jpg',
        ]])->postJson(route('visitor.verify_live_face'), [
            'selfie' => UploadedFile::fake()->image('different-person.jpg', 1280, 960),
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'face_mismatch');
        $this->assertEquals('rejected', session('verification.face_verification_status'));
    }

    private function faceAnnotation(): array
    {
        $points = [
            'LEFT_EYE' => [100, 100], 'RIGHT_EYE' => [200, 100],
            'NOSE_TIP' => [150, 155], 'MOUTH_CENTER' => [150, 205],
            'MOUTH_LEFT' => [110, 205], 'MOUTH_RIGHT' => [190, 205],
        ];

        return [
            'detectionConfidence' => .96,
            'landmarkingConfidence' => .92,
            'blurredLikelihood' => 'VERY_UNLIKELY',
            'underExposedLikelihood' => 'VERY_UNLIKELY',
            'landmarks' => collect($points)->map(fn ($position, $type) => [
                'type' => $type,
                'position' => ['x' => $position[0], 'y' => $position[1], 'z' => 0],
            ])->values()->all(),
        ];
    }
}
