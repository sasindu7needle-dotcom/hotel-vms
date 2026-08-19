<?php

namespace Tests\Feature;

use App\Models\VisitorCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_registration_is_saved_for_admin_and_receipt_manager(): void
    {
        Storage::fake('local');
        $category = VisitorCategory::create([
            'name' => 'Staff',
            'code' => 'staff',
            'entrance_fee' => 500,
            'badge_color' => '#c8e063',
            'is_active' => true,
        ]);

        $verificationId = '11111111-1111-4111-8111-111111111111';
        $this->withSession(['manual_identity_verification' => [
            'verification_id' => $verificationId,
            'document_type' => 'nic',
            'document_number' => '199012345678',
            'photo_path' => 'verified-visitors/'.$verificationId.'-document-front.jpg',
            'photo_mime' => 'image/jpeg',
            'back_photo_path' => 'verified-visitors/'.$verificationId.'-document-back.jpg',
            'back_photo_mime' => 'image/jpeg',
        ]])->post(route('visitor.manual.store'), [
            'full_name' => 'Manual Visitor',
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

        $this->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('Thank you for registering')
            ->assertSee('Manual Visitor')
            ->assertSee('Engineer')
            ->assertSee('Example Ltd')
            ->assertSee('<svg', false);

        $this->assertDatabaseHas('verified_visitors', [
            'full_name' => 'Manual Visitor',
            'document_number' => '199012345678',
            'mobile_number' => '+94771234567',
            'category' => 'Staff',
            'entrance_fee' => '500.00',
            'payment_status' => 'pending',
        ]);
    }
}
