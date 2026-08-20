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
        Storage::disk('visitor-media')->assertExists([
            session('verification.photo_path'),
            session('verification.back_photo_path'),
        ]);
        $response->assertJsonPath('redirect_url', route('visitor.photo_capture'));
    }

    public function test_it_rejects_invalid_document_type_or_missing_image(): void
    {
        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'invalid_type',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type', 'document_front_image']);
    }

    public function test_it_normalizes_and_validates_both_sri_lankan_nic_formats(): void
    {
        $gemini = app(GeminiDocumentService::class);

        foreach ([
            '123456789V' => '123456789V',
            '123456789v' => '123456789V',
            '123 456-789 v' => '123456789V',
            '1998 1234-5678' => '199812345678',
        ] as $input => $expected) {
            $this->assertSame($expected, $gemini->normalizeNicNumber($input));
            $this->assertTrue($gemini->isValidSriLankanNic($input));
        }

        $this->assertFalse($gemini->isValidSriLankanNic('1234567890'));
        $this->assertFalse($gemini->isValidSriLankanNic('12345678901'));
        $this->assertFalse($gemini->isValidSriLankanNic('200100012345'));
        $this->assertFalse($gemini->isValidSriLankanNic('200149912345'));
        $this->assertFalse($gemini->isValidSriLankanNic('209912312345'));
    }

    public function test_driving_licence_fallback_separates_field_4c_nic_from_field_5_licence_number(): void
    {
        $method = new \ReflectionMethod(VisitorCheckinController::class, 'combineTesseractIdentityFields');
        $text = "DRIVING LICENCE\n4c 200109402239\n5 B1234567\nSURNAME\nPERERA\nOTHER NAMES\nNIMAL KAMAL\nPERMANENT PLACE OF RESIDENCE\nNO. 8\nKANDY ROAD\nKANDY";

        $parsed = $method->invoke(app(VisitorCheckinController::class), $text, $text, 'driving_license', '');

        $this->assertSame('200109402239', $parsed['nic_number']);
        $this->assertSame('B1234567', $parsed['driving_license_number']);
        $this->assertSame('NIMAL KAMAL PERERA', $parsed['full_name']);
        $this->assertSame('NO. 8, KANDY ROAD, KANDY', $parsed['address']);
    }

    public function test_gemini_parser_accepts_nic_aliases_markdown_and_text_fallback(): void
    {
        $gemini = app(GeminiDocumentService::class);

        $aliased = $gemini->parseGeminiResponse('```json\n{"nic_number":"123 456-789v","full_name":"Nimal Perera","address":"12 Galle Road"}\n```', 'nic');
        $this->assertSame('123456789V', $aliased['document_number']);
        $this->assertSame('nic_number', $aliased['_gemini_document_number_key']);

        $camelCase = $gemini->parseGeminiResponse('Gemini result: {"documentNumber":"199812345678"} complete', 'nic');
        $this->assertSame('199812345678', $camelCase['document_number']);
        $this->assertSame('documentNumber', $camelCase['_gemini_document_number_key']);

        $fallback = $gemini->parseGeminiResponse('I could read the NIC Number: 123 456 789 X, but no JSON was produced.', 'nic');
        $this->assertFalse($fallback['_gemini_json_decoded']);
        $this->assertSame('123456789X', $fallback['document_number']);
    }

    public function test_missing_document_number_returns_a_controlled_notice(): void
    {
        $this->mockGemini([
            'full_name' => 'Nimal Perera',
            'address' => '12 Galle Road, Colombo',
        ]);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 800, 500),
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'document_number_not_detected');
    }

    public function test_gemini_service_uses_the_combined_card_read_when_identity_fields_are_complete(): void
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

        $this->assertSame('Senarath Wickramanayaka Mudiyanselage Bimasara', $result['full_name']);
        Http::assertSentCount(1);
    }

    public function test_back_only_extraction_cannot_replace_conflicting_combined_identity_fields(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiApiResponse([
                    'document_number' => '199012345678',
                    'full_name' => 'Madikes Paramanandake Poojana Sasithu Legonj Fernando',
                    'full_name_lines' => [],
                    'address' => '07, Thiyelagoda Mallama, Puttalam',
                    'address_lines' => [],
                    'full_name_original' => '',
                    'address_original' => '',
                ]))
                ->push($this->geminiApiResponse([
                    'document_number' => '',
                    'full_name' => 'Different Nearby Name',
                    'full_name_lines' => [],
                    'address' => 'Different Nearby Address',
                    'address_lines' => [],
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
            'image/jpeg',
            false,
            'nic'
        );

        $this->assertSame('Madikes Paramanandake Poojana Sasithu Legonj Fernando', $result['full_name']);
        $this->assertSame('07, Thiyelagoda Mallama, Puttalam', $result['address']);
        Http::assertSentCount(1);
    }

    public function test_gemini_service_preserves_repeated_physical_name_lines(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiApiResponse([
                'document_number' => '199012345678',
                'full_name' => 'Nimal Perera Nimal Perera',
                'full_name_lines' => ['Nimal Perera', 'Nimal Perera'],
                'address' => '12 Galle Road, Colombo',
                'address_lines' => [],
                'full_name_original' => '',
                'address_original' => '',
            ])),
        ]);

        $front = UploadedFile::fake()->image('front.jpg', 600, 400);
        $result = app(GeminiDocumentService::class)->extract($front->getRealPath(), 'image/jpeg', null, null, false, 'nic');

        $this->assertSame('Nimal Perera Nimal Perera', $result['full_name']);
    }

    public function test_old_nic_translates_the_exact_sinhala_name_and_address_to_english(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiApiResponse([
                    'document_number' => '993100900V',
                    'full_name' => 'මදුෂ් පරමානන්දකේ පූජන සදරු',
                    'full_name_lines' => [],
                    'address' => '07, ප්‍රසේවස් මාවත, පුත්තලම',
                    'address_lines' => [],
                    'full_name_original' => 'මදුෂ් පරමානන්දකේ පූජන සදරු',
                    'address_original' => '07, ප්‍රසේවස් මාවත, පුත්තලම',
                ]))
                ->push($this->geminiApiResponse([
                    'full_name' => 'Madush Paramanandake Poojana Sadaru',
                    'address' => '07, Prasewas Mawatha, Puttalam',
                ])),
        ]);

        $front = UploadedFile::fake()->image('old-nic-front.jpg', 600, 400);
        $result = app(GeminiDocumentService::class)->extract($front->getRealPath(), 'image/jpeg', null, null, false, 'nic');

        $this->assertSame('Madush Paramanandake Poojana Sadaru', $result['full_name']);
        $this->assertSame('07, Prasewas Mawatha, Puttalam', $result['address']);
        $this->assertSame('මදුෂ් පරමානන්දකේ පූජන සදරු', $result['full_name_original']);
        $this->assertSame('07, ප්‍රසේවස් මාවත, පුත්තලම', $result['address_original']);
    }

    public function test_old_nic_keeps_the_image_aware_english_name(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiApiResponse([
                'document_number' => '993100900V',
                'full_name' => 'Madikes Paramanandake Poojana Sasithu',
                'full_name_lines' => [],
                'address' => '07, Prasewas Mawatha, Puttalam',
                'address_lines' => [],
                'full_name_original' => 'මැඩිකේස් පරමානන්දකේ පූජන සසිතු',
                'address_original' => '07, ප්‍රසේවස් මාවත, පුත්තලම',
            ])),
        ]);

        $front = UploadedFile::fake()->image('old-nic-front.jpg', 600, 400);
        $result = app(GeminiDocumentService::class)->extract($front->getRealPath(), 'image/jpeg', null, null, false, 'nic');

        $this->assertSame('Madikes Paramanandake Poojana Sasithu', $result['full_name']);
        Http::assertSentCount(1);
    }

    public function test_old_nic_with_english_identity_fields_passes_verification(): void
    {
        $this->mockGemini([
            'document_number' => '993100900V',
            'full_name' => 'Madush Paramanandake Poojana Sadaru',
            'address' => '07, Prasewas Mawatha, Puttalam',
            'full_name_original' => 'මදුෂ් පරමානන්දකේ පූජන සදරු',
            'address_original' => '07, ප්‍රසේවස් මාවත, පුත්තලම',
        ]);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('old-nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('old-nic-back.jpg', 800, 500),
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Madush Paramanandake Poojana Sadaru')
            ->assertJsonPath('data.address', '07, Prasewas Mawatha, Puttalam');
    }

    public function test_old_nic_uses_two_native_reads_then_a_separate_transliteration(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        $nativeName = 'කසුන් මධුශංක පෙරේරා';
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiApiResponse([
                    'name_native' => $nativeName,
                    'uncertain_segments' => [],
                    'needs_review' => false,
                    'printed_english_name' => '',
                    'confidence' => 94,
                ]))
                ->push($this->geminiApiResponse([
                    'name_native' => "  කසුන්\nමධුශංක   පෙරේරා  ",
                    'uncertain_segments' => [],
                    'needs_review' => false,
                    'printed_english_name' => '',
                    'confidence' => 96,
                ]))
                ->push($this->geminiApiResponse([
                    'english_name_candidate' => 'Kasun Madhushanka Perera',
                    'alternative_spellings' => ['Kasun Madushanka Perera'],
                    'ambiguous' => true,
                    'ambiguity_reason' => 'More than one conventional Roman spelling is possible.',
                ])),
        ]);

        $front = UploadedFile::fake()->image('old-nic-front.jpg', 800, 500);
        $back = UploadedFile::fake()->image('old-nic-back.jpg', 800, 500);
        $review = app(GeminiDocumentService::class)->extractNicNameReview(
            $front->getRealPath(),
            'image/jpeg',
            $back->getRealPath(),
            'image/jpeg',
        );

        $this->assertSame($nativeName, $review['name_native']);
        $this->assertSame('Kasun Madhushanka Perera', $review['suggested_english_name']);
        $this->assertSame(['Kasun Madushanka Perera'], $review['name_alternatives']);
        $this->assertTrue($review['native_reads_agree']);
        $this->assertTrue($review['name_needs_confirmation']);
        Http::assertSentCount(3);
    }

    public function test_old_nic_verification_read_failure_still_returns_the_first_read(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        $nativeName = 'කසුන් පෙරේරා';
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiApiResponse([
                    'name_native' => $nativeName,
                    'uncertain_segments' => [],
                    'needs_review' => false,
                    'printed_english_name' => '',
                    'confidence' => 90,
                ]))
                ->push(['error' => ['message' => 'Temporary failure']], 503)
                ->push($this->geminiApiResponse([
                    'english_name_candidate' => 'Kasun Perera',
                    'alternative_spellings' => [],
                    'ambiguous' => false,
                    'ambiguity_reason' => '',
                ])),
        ]);

        $front = UploadedFile::fake()->image('old-nic-front.jpg', 800, 500);
        $review = app(GeminiDocumentService::class)->extractNicNameReview(
            $front->getRealPath(),
            'image/jpeg',
        );

        $this->assertSame($nativeName, $review['name_native']);
        $this->assertSame('Kasun Perera', $review['suggested_english_name']);
        $this->assertTrue($review['name_needs_confirmation']);
        Http::assertSentCount(3);
    }

    public function test_difficult_old_nic_selects_the_stronger_read_and_requires_confirmation(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        $strongerName = 'කසුන් මධුශංක පෙරේරා';
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiApiResponse([
                    'name_native' => 'කසුන් මදුශංක පෙරේරා',
                    'uncertain_segments' => ['මදුශංක'],
                    'needs_review' => true,
                    'printed_english_name' => '',
                    'confidence' => 58,
                ]))
                ->push($this->geminiApiResponse([
                    'name_native' => $strongerName,
                    'uncertain_segments' => [],
                    'needs_review' => false,
                    'printed_english_name' => '',
                    'confidence' => 91,
                ]))
                ->push($this->geminiApiResponse([
                    'english_name_candidate' => 'Kasun Madhushanka Perera',
                    'alternative_spellings' => ['Kasun Madushanka Perera'],
                    'ambiguous' => true,
                    'ambiguity_reason' => 'The middle name permits multiple Roman spellings.',
                ])),
        ]);

        $front = UploadedFile::fake()->image('faded-old-nic-front.jpg', 800, 500);
        $review = app(GeminiDocumentService::class)->extractNicNameReview(
            $front->getRealPath(),
            'image/jpeg',
        );

        $this->assertSame($strongerName, $review['name_native']);
        $this->assertFalse($review['native_reads_agree']);
        $this->assertTrue($review['name_needs_confirmation']);
        $this->assertSame('needs_attention', $review['review_status']);
        Http::assertSentCount(3);
    }

    public function test_old_nic_review_updates_compatible_response_and_session_fields(): void
    {
        $nativeName = 'කසුන් මධුශංක පෙරේරා';
        $this->mock(GeminiDocumentService::class, function ($mock) use ($nativeName) {
            $mock->shouldReceive('extract')->once()->andReturn([
                'document_number' => '993100900V',
                'nic_number' => '993100900V',
                'driving_license_number' => '',
                'full_name' => 'Existing Fallback Name',
                'address' => '07, Main Road, Puttalam',
                'full_name_original' => $nativeName,
                'address_original' => '',
            ]);
            $mock->shouldReceive('extractNicNameReview')->once()->andReturn([
                'sinhala_name' => $nativeName,
                'tamil_name' => '',
                'printed_english_name' => '',
                'suggested_english_name' => 'Kasun Madhushanka Perera',
                'sinhala_transliteration' => 'Kasun Madhushanka Perera',
                'tamil_transliteration' => '',
                'english_name_alternatives' => ['Kasun Madushanka Perera'],
                'scripts_agree' => true,
                'review_status' => 'needs_attention',
                'name_native' => $nativeName,
                'name_alternatives' => ['Kasun Madushanka Perera'],
                'name_needs_confirmation' => true,
                'uncertain_segments' => [],
                'native_reads_agree' => true,
            ]);
        });

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('old-nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('old-nic-back.jpg', 800, 500),
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Kasun Madhushanka Perera')
            ->assertJsonPath('data.name_native', $nativeName)
            ->assertJsonPath('data.name_needs_confirmation', true)
            ->assertJsonPath('data.name_alternatives.0', 'Kasun Madushanka Perera');

        $this->assertSame('Kasun Madhushanka Perera', session('verification.full_name'));
        $this->assertSame($nativeName, session('verification.full_name_native'));
        $this->assertTrue(session('verification.full_name_needs_confirmation'));
    }

    public function test_new_nic_does_not_run_the_old_nic_name_review(): void
    {
        $this->mock(GeminiDocumentService::class, function ($mock) {
            $mock->shouldReceive('extract')->once()->andReturn([
                'document_number' => '200124103810',
                'nic_number' => '200124103810',
                'driving_license_number' => '',
                'full_name' => 'Nimal Perera',
                'address' => '12 Galle Road, Colombo',
                'full_name_original' => '',
                'address_original' => '',
            ]);
            $mock->shouldNotReceive('extractNicNameReview');
        });

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('new-nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('new-nic-back.jpg', 800, 500),
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Nimal Perera')
            ->assertJsonPath('data.document_number', '200124103810');
    }

    public function test_old_nic_review_failure_preserves_the_existing_extraction(): void
    {
        $this->mock(GeminiDocumentService::class, function ($mock) {
            $mock->shouldReceive('extract')->once()->andReturn([
                'document_number' => '993100900V',
                'nic_number' => '993100900V',
                'driving_license_number' => '',
                'full_name' => 'Existing Fallback Name',
                'address' => '07, Main Road, Puttalam',
                'full_name_original' => '',
                'address_original' => '',
            ]);
            $mock->shouldReceive('extractNicNameReview')->once()->andThrow(new \RuntimeException('Temporary Gemini failure'));
        });

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('old-nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('old-nic-back.jpg', 800, 500),
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Existing Fallback Name')
            ->assertJsonPath('data.address', '07, Main Road, Puttalam');
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

    public function test_missing_gemini_configuration_never_falls_back_to_bad_form_values(): void
    {
        config()->set('services.gemini.api_key', null);
        config()->set('services.gemini.allow_tesseract_fallback', false);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 800, 500),
        ])->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'gemini_not_configured');

        $this->assertNull(session('verification'));
    }

    public function test_document_labels_cannot_be_accepted_as_the_holder_name(): void
    {
        $this->mockGemini([
            'document_number' => '200127703388',
            'full_name' => 'Place of Birth HAMBANTOTA',
            'address' => '89/1, Galle Road, Colombo',
        ]);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'nic',
            'document_front_image' => UploadedFile::fake()->image('nic-front.jpg', 800, 500),
            'document_back_image' => UploadedFile::fake()->image('nic-back.jpg', 800, 500),
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'incomplete_identity_fields');

        $this->assertNull(session('verification'));
    }

    public function test_driving_licence_identity_survives_face_capture_and_prefills_registration(): void
    {
        $this->mockGemini([
            'document_number' => 'B1234567',
            'nic_number' => '200109402239',
            'driving_license_number' => 'B1234567',
            'full_name' => 'Nimal Kamal Perera',
            'address' => 'No. 8, Kandy Road, Kandy',
        ]);

        $this->postJson(route('visitor.verify_vision'), [
            'document_type' => 'driving_license',
            'document_front_image' => UploadedFile::fake()->image('licence-front.jpg', 900, 560),
        ])->assertOk()
            ->assertJsonPath('data.document_number', '200109402239')
            ->assertJsonPath('data.driving_license_number', 'B1234567');

        $this->postJson(route('visitor.capture_photo'), [
            'selfie' => UploadedFile::fake()->image('face.jpg', 1280, 960),
        ])->assertOk();

        $this->get(route('visitor.create', ['type' => 'driving_license']))
            ->assertOk()
            ->assertSee('Nimal Kamal Perera')
            ->assertSee('200109402239')
            ->assertSee('No. 8, Kandy Road, Kandy');
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
        Storage::disk('visitor-media')->put('verified-visitors/document.jpg', 'document');
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
        Storage::disk('visitor-media')->assertExists('verified-visitors/11111111-2222-4333-8444-555555555555-photo.jpg');
    }

    public function test_face_capture_page_allows_reviewing_and_replacing_the_photo(): void
    {
        $response = $this->withSession(['verification' => [
            'session_id' => '11111111-2222-4333-8444-555555555555',
            'verification_id' => '11111111-2222-4333-8444-555555555555',
            'document_type' => 'nic',
            'document_number' => '199012345678',
            'full_name' => 'Nimal Kamal Perera',
            'address' => 'No. 12, Galle Road, Colombo 03',
            'photo_path' => 'verified-visitors/document.jpg',
        ]])->get(route('visitor.photo_capture'));

        $response->assertOk()
            ->assertSee('Captured visitor photo preview')
            ->assertSee('Retake / replace photo')
            ->assertSee('Use photo &amp; proceed', false);
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
            $mock->shouldReceive('extractNicNameReview')->andReturn([]);
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
