<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmationMail;
use App\Models\VisitorCategory;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_registration_components_have_compiled_styles(): void
    {
        $this->get(route('visitor.manual.create'))
            ->assertOk()
            ->assertSee('manual-flow-stage', false)
            ->assertSee('manual-flow-upload', false)
            ->assertSee('name="email"', false)
            ->assertSee('The payment confirmation, entrance card, and invoice will be sent to this email.')
            ->assertDontSee('name="payment_slip"', false)
            ->assertDontSee('Payment slip (optional)');

        $compiledCss = file_get_contents(public_path('css/app.css'));

        $this->assertIsString($compiledCss);
        $this->assertStringContainsString('body.manual-registration-flow .manual-flow-stage', $compiledCss);
        $this->assertStringContainsString('body.manual-registration-flow .manual-flow-upload', $compiledCss);
    }

    public function test_manual_registration_category_dropdown_contains_only_active_category_types(): void
    {
        $activeCategory = VisitorCategory::create([
            'name' => 'Foreign Participants',
            'code' => 'foreign-participants',
            'entrance_fee' => 1500,
            'badge_color' => '#c8e063',
            'is_active' => true,
        ]);
        VisitorCategory::create([
            'name' => 'Inactive Category',
            'code' => 'inactive-category',
            'entrance_fee' => 0,
            'badge_color' => '#c8e063',
            'is_active' => false,
        ]);

        $this->get(route('visitor.manual.create'))
            ->assertOk()
            ->assertSee('<option value="'.$activeCategory->id.'"', false)
            ->assertSee('Foreign Participants')
            ->assertDontSee('<option value="">Manual registration</option>', false)
            ->assertDontSee('Inactive Category');
    }

    public function test_manual_registration_requires_an_active_category_type(): void
    {
        $this->from(route('visitor.manual.create'))
            ->post(route('visitor.manual.store'), [])
            ->assertRedirect(route('visitor.manual.create'))
            ->assertSessionHasErrors('category_id');
    }

    public function test_manual_registration_rejects_an_nic_already_used_for_the_event(): void
    {
        $category = VisitorCategory::create([
            'name' => 'Staff',
            'code' => 'staff',
            'entrance_fee' => 500,
            'is_active' => true,
        ]);
        VerifiedVisitor::create([
            'verification_id' => '11111111-1111-4111-8111-000000000001',
            'document_type' => 'nic',
            'document_number' => '199012345678',
            'nic_registration_key' => '199012345678',
            'full_name' => 'Existing NIC Visitor',
        ]);
        $verificationId = '11111111-1111-4111-8111-000000000002';

        $this->withSession(['manual_identity_verification' => [
            'verification_id' => $verificationId,
            'document_type' => 'nic',
            'document_number' => '1990 1234-5678',
            'photo_path' => 'verified-visitors/existing-document.jpg',
        ]])->from(route('visitor.manual.create'))
            ->post(route('visitor.manual.store'), [
                'full_name' => 'Duplicate NIC Visitor',
                'email' => 'duplicate@example.test',
                'document_type' => 'nic',
                'identity_verification_id' => $verificationId,
                'mobile_number' => '+94771234567',
                'whatsapp_number' => '',
                'address' => '12 Galle Road, Colombo',
                'occupation' => 'Engineer',
                'company' => 'Example Ltd',
                'category_id' => $category->id,
                'entrance_fee' => '500.00',
                'face_photo' => UploadedFile::fake()->image('face.jpg'),
            ])->assertRedirect(route('visitor.manual.create'))
            ->assertSessionHasErrors([
                'identity' => 'This NIC number is already registered for this event. Only one registration is allowed per NIC.',
            ]);

        $this->assertDatabaseCount('verified_visitors', 1);
    }

    public function test_walk_in_registration_is_saved_for_admin_and_receipt_manager(): void
    {
        Mail::fake();
        $mediaDisk = (string) config('vms.media_disk', 'visitor-media');
        Storage::fake($mediaDisk);
        $category = VisitorCategory::create([
            'name' => 'Staff',
            'code' => 'staff',
            'entrance_fee' => 500,
            'badge_color' => '#c8e063',
            'is_active' => true,
        ]);

        $verificationId = '11111111-1111-4111-8111-111111111111';
        $documentPath = 'verified-visitors/'.$verificationId.'-document-front.jpg';
        Storage::disk($mediaDisk)->put($documentPath, 'identity-document-image');
        $this->withSession(['manual_identity_verification' => [
            'verification_id' => $verificationId,
            'document_type' => 'nic',
            'document_number' => '199012345678',
            'photo_path' => $documentPath,
            'photo_mime' => 'image/jpeg',
            'back_photo_path' => 'verified-visitors/'.$verificationId.'-document-back.jpg',
            'back_photo_mime' => 'image/jpeg',
        ]])->post(route('visitor.manual.store'), [
            'full_name' => 'Manual Visitor',
            'email' => 'manual.visitor@example.test',
            'document_type' => 'nic',
            'identity_verification_id' => $verificationId,
            'mobile_number' => '+94771234567',
            'whatsapp_number' => '',
            'address' => '12 Galle Road, Colombo',
            'occupation' => 'Engineer',
            'company' => 'Example Ltd',
            'category_id' => $category->id,
            'entrance_fee' => '500.00',
            'face_photo' => UploadedFile::fake()->image('face.jpg'),
        ])->assertRedirect(route('visitor.thank-you'));

        $visitor = VerifiedVisitor::where('verification_id', $verificationId)->firstOrFail();
        $this->assertNotSame($visitor->photo_path, $visitor->selfie_path);
        $this->assertStringContainsString('-face.', $visitor->selfie_path);
        $this->assertNull($visitor->payment_slip_path);
        Storage::disk($mediaDisk)->assertExists($visitor->selfie_path);

        $profilePhoto = $this->get(route('visitor.session_photo', ['type' => 'selfie']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('no-store', (string) $profilePhoto->headers->get('Cache-Control'));
        $profilePhotoContents = $profilePhoto->streamedContent();
        $this->assertSame(
            Storage::disk($mediaDisk)->get($visitor->selfie_path),
            $profilePhotoContents
        );
        $this->assertNotSame(
            Storage::disk($mediaDisk)->get($visitor->photo_path),
            $profilePhotoContents
        );

        $this->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('Thank you for registering')
            ->assertSee('Manual Visitor')
            ->assertSee('Engineer')
            ->assertSee('Example Ltd')
            ->assertSee(route('visitor.session_photo', ['type' => 'selfie']), false)
            ->assertDontSee(route('visitor.session_photo', ['type' => 'photo']), false)
            ->assertSee('<svg', false);

        $adminSession = ['admin_authenticated' => true, 'admin_username' => 'admin'];
        $adminVisitors = $this->withSession($adminSession)->get(route('admin.visitors.index'));
        $adminVisitors->assertOk()
            ->assertSee('manual.visitor@example.test')
            ->assertDontSee(route('admin.visitors.payment_slip', $visitor), false);

        $this->withSession($adminSession)->get(route('admin.receipts.index'))
            ->assertOk()
            ->assertSee('Recent manual visitors')
            ->assertSee('Manual Visitor')
            ->assertSee(route('admin.receipts.index', ['manual_id' => $visitor->id]), false);

        $this->withSession($adminSession)->get(route('admin.receipts.index', [
            'manual_id' => $visitor->id,
        ]))->assertOk()
            ->assertSee('manual.visitor@example.test')
            ->assertSee('name="payment_slip"', false)
            ->assertDontSee('Confirm entrance payment')
            ->assertDontSee('View uploaded payment slip');

        $this->withSession($adminSession)->post(route('admin.receipts.payment_slip.store', $visitor), [
            'payment_slip' => UploadedFile::fake()->image('payment-slip.png', 640, 900),
        ])->assertRedirect(route('admin.receipts.index', ['manual_id' => $visitor->id]))
            ->assertSessionHas('status', 'Payment confirmed for Manual Visitor. The entrance card and invoice were emailed successfully.');

        $visitor->refresh();
        $this->assertStringContainsString('-payment-slip.', $visitor->payment_slip_path);
        Storage::disk($mediaDisk)->assertExists($visitor->payment_slip_path);
        $this->assertNotNull($visitor->paid_at);
        $this->assertNotNull($visitor->payment_confirmation_emailed_at);
        Mail::assertSent(PaymentConfirmationMail::class, function (PaymentConfirmationMail $mail) use ($visitor) {
            $mail->assertTo($visitor->email);
            $this->assertSame('500.00', $mail->invoice['amount']);
            $attachments = collect($mail->attachments())->map(fn ($attachment) => $attachment->attachWith(
                fn () => null,
                fn ($data, $resolvedAttachment) => [
                    'data' => $data(),
                    'mime' => $resolvedAttachment->mime,
                ],
            ));
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $attachments->firstWhere('mime', 'image/png')['data'] ?? '');
            $this->assertStringStartsWith('%PDF-', $attachments->firstWhere('mime', 'application/pdf')['data'] ?? '');

            return true;
        });

        $this->withSession($adminSession)->get(route('admin.receipts.index', [
            'manual_id' => $visitor->id,
        ]))->assertOk()
            ->assertSee('View uploaded payment slip')
            ->assertSee(route('admin.visitors.payment_slip', $visitor), false);

        $paymentSlip = $this->withSession($adminSession)
            ->get(route('admin.visitors.payment_slip', $visitor))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->assertSame(
            Storage::disk($mediaDisk)->get($visitor->payment_slip_path),
            $paymentSlip->streamedContent()
        );

        $this->assertDatabaseHas('verified_visitors', [
            'full_name' => 'Manual Visitor',
            'email' => 'manual.visitor@example.test',
            'document_number' => '199012345678',
            'mobile_number' => '+94771234567',
            'category' => 'Staff',
            'entrance_fee' => '500.00',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'registration_status' => 'paid',
            'payment_slip_mime' => 'image/png',
        ]);
    }

    public function test_profile_photo_endpoint_never_falls_back_to_the_identity_document(): void
    {
        $mediaDisk = (string) config('vms.media_disk', 'visitor-media');
        Storage::fake($mediaDisk);
        $documentPath = 'verified-visitors/document-only.jpg';
        Storage::disk($mediaDisk)->put($documentPath, 'identity-document-image');
        $visitor = VerifiedVisitor::create([
            'verification_id' => '22222222-2222-4222-8222-222222222222',
            'full_name' => 'Document Only Visitor',
            'photo_path' => $documentPath,
            'photo_mime' => 'image/jpeg',
            'selfie_path' => null,
            'payment_status' => 'pending',
        ]);

        $this->withSession(['visitor_registration' => [
            'record_id' => $visitor->id,
            'manual_registration' => true,
            'photo_path' => $documentPath,
        ]])->get(route('visitor.session_photo', ['type' => 'selfie']))
            ->assertNotFound();
    }

    public function test_manual_payment_slip_actions_do_not_change_standard_receipt_confirmation(): void
    {
        Storage::fake((string) config('vms.media_disk', 'visitor-media'));
        $standardVisitor = VerifiedVisitor::create([
            'verification_id' => '33333333-3333-4333-8333-333333333333',
            'full_name' => 'Standard Visitor',
            'ocr_provider' => 'gemini',
            'payment_status' => 'pending',
        ]);
        $manualVisitor = VerifiedVisitor::create([
            'verification_id' => '44444444-4444-4444-8444-444444444444',
            'full_name' => 'Manual Visitor',
            'ocr_provider' => 'manual_registration',
            'payment_status' => 'pending',
        ]);
        $adminSession = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($adminSession)->post(route('admin.receipts.confirm', $standardVisitor), [
            'entrance_fee' => '750.00',
            'payment_method' => 'cash',
        ])->assertRedirect();
        $this->assertDatabaseHas('verified_visitors', [
            'id' => $standardVisitor->id,
            'entrance_fee' => '750.00',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->withSession($adminSession)->post(route('admin.receipts.payment_slip.store', $standardVisitor), [
            'payment_slip' => UploadedFile::fake()->image('payment-slip.png'),
        ])->assertNotFound();
        $this->withSession($adminSession)->post(route('admin.receipts.confirm', $manualVisitor), [
            'entrance_fee' => '750.00',
            'payment_method' => 'cash',
        ])->assertNotFound();
        $this->withSession($adminSession)->post(route('admin.receipts.payment_slip.store', $manualVisitor), [
            'payment_slip' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('payment_slip');
    }
}
