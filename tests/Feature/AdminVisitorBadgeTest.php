<?php

namespace Tests\Feature;

use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminVisitorBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_a_printable_card_with_the_gate_qr_payload(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verified-visitors/visitor-live.jpg', 'verified-live-photo');

        $visitor = VerifiedVisitor::create([
            'verification_id' => (string) Str::uuid(),
            'full_name' => 'Printed Visitor',
            'category' => 'Visitor',
            'registration_status' => 'registered',
            'payment_status' => 'paid',
            'face_verification_status' => 'verified',
            'selfie_path' => 'verified-visitors/visitor-live.jpg',
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_username' => 'admin',
        ])->get(route('admin.visitors.badge', $visitor))
            ->assertOk()
            ->assertSee('Print Card')
            ->assertSee('Printed Visitor')
            ->assertSee($visitor->verification_id)
            ->assertSee('@media screen and (max-width: 480px)', false)
            ->assertSee('print-color-adjust: exact', false)
            ->assertSee('size: 90mm 140mm', false)
            ->assertSee('<svg', false);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_username' => 'admin',
        ])->get(route('admin.visitors.index'))
            ->assertOk()
            ->assertSee('Print Card')
            ->assertSee('>Print<', false);
    }

    public function test_card_printing_is_rejected_without_a_captured_photo(): void
    {
        Storage::fake('local');
        $visitor = VerifiedVisitor::create([
            'verification_id' => (string) Str::uuid(),
            'full_name' => 'Unverified Visitor',
            'registration_status' => 'registered',
            'payment_status' => 'paid',
            'face_verification_status' => 'pending',
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_username' => 'admin',
        ])->get(route('admin.visitors.badge', $visitor))
            ->assertRedirect(route('admin.visitors.index'))
            ->assertSessionHasErrors('badge');
    }
}
