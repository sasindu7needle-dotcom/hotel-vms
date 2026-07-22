<?php

namespace Tests\Feature;

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

    public function test_it_handles_vision_api_network_failure_gracefully(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Vision API offline'));

        $file = UploadedFile::fake()->image('license.png', 500, 300);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => $file,
            'document_back_image' => UploadedFile::fake()->image('license-back.png', 500, 300),
        ])->assertStatus(502);
    }

    public function test_it_verifies_a_live_camera_face_and_unlocks_registration(): void
    {
        Http::fake(['vision.googleapis.com/*' => Http::response([
            'responses' => [['faceAnnotations' => [$this->faceAnnotation()]]],
        ])]);

        $documentSignature = ['nose_eye' => .55, 'mouth_eye' => 1.05, 'nose_mouth' => .5, 'mouth_width' => .8];
        $response = $this->withSession(['verification' => [
            'session_id' => '11111111-2222-4333-8444-555555555555',
            'verification_id' => '11111111-2222-4333-8444-555555555555',
            'document_type' => 'nic',
            'document_face_signature' => $documentSignature,
        ]])->postJson(route('visitor.verify_live_face'), [
            'selfie' => UploadedFile::fake()->image('live-camera.jpg', 1280, 960),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('verified', session('verification.face_verification_status'));
        Storage::disk('local')->assertExists('verified-visitors/11111111-2222-4333-8444-555555555555-live.jpg');
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
