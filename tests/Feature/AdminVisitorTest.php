<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminVisitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_manager_navigation_is_visible_on_the_visitor_page(): void
    {
        $this->withSession([
            'admin_authenticated' => true,
            'admin_username' => 'admin',
            'admin_permissions' => ['Visitors'],
        ])
            ->get(route('admin.visitors.index'))
            ->assertOk()
            ->assertSee('Receipt Manager');
    }

    public function test_schedule_manager_navigation_is_visible_to_account_managers_on_the_visitor_page(): void
    {
        $this->withSession([
            'admin_authenticated' => true,
            'admin_username' => 'admin',
            'admin_permissions' => ['Visitors', 'Users & Access'],
        ])
            ->get(route('admin.visitors.index'))
            ->assertOk()
            ->assertSee('Schedule Manager');
    }

    public function test_admin_can_update_visitor_payment_status_and_details(): void
    {
        $visitor = VerifiedVisitor::create([
            'verification_id' => '122c9075-1d4c-44df-9412-dde29c60616f',
            'full_name' => 'John Doe',
            'document_type' => 'nic',
            'document_number' => '199012345678',
            'address' => '123 Main Street',
            'payment_status' => 'pending',
            'face_verification_status' => 'verified',
            'is_blocked' => false,
        ]);

        $response = $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->patch(route('admin.visitors.update', $visitor), [
                'full_name' => 'John Doe Updated',
                'document_type' => 'nic',
                'document_number' => '199012345678',
                'address' => '123 Main Street',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'face_verification_status' => 'verified',
                'is_blocked' => '0',
            ]);

        $response->assertRedirect(route('admin.visitors.index'));
        $response->assertSessionHas('status', 'Visitor details updated successfully.');

        $this->assertDatabaseHas('verified_visitors', [
            'id' => $visitor->id,
            'full_name' => 'John Doe Updated',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);
    }

    public function test_get_individual_visitor_route_redirects_to_index(): void
    {
        $visitor = VerifiedVisitor::create([
            'verification_id' => '23d4d58d-412f-4c48-91c3-1352038913fa',
            'full_name' => 'Jane Doe',
            'payment_status' => 'pending',
            'face_verification_status' => 'verified',
            'is_blocked' => false,
        ]);

        $response = $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get('/admin/visitors/'.$visitor->id);

        $response->assertRedirect(route('admin.visitors.index'));
    }

    public function test_admin_visitor_images_are_current_private_files_and_are_not_cached(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verified-visitors/current-live.jpg', 'current-live-photo');

        $visitor = VerifiedVisitor::create([
            'verification_id' => '43d4d58d-412f-4c48-91c3-1352038913fa',
            'full_name' => 'Current Photo Visitor',
            'payment_status' => 'paid',
            'face_verification_status' => 'verified',
            'selfie_path' => 'verified-visitors/current-live.jpg',
            'selfie_mime' => 'image/jpeg',
            'is_blocked' => false,
        ]);
        $version = $visitor->updated_at->format('Uu');
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $index = $this->withSession($session)->get(route('admin.visitors.index'));
        $index->assertOk()
            ->assertSee(route('admin.visitors.selfie', [
                'visitor' => $visitor,
                'v' => $version,
            ]), false);

        $image = $this->withSession($session)->get(route('admin.visitors.selfie', [
            'visitor' => $visitor,
            'v' => $version,
        ]));

        $image->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', (string) $image->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $image->headers->get('Cache-Control'));
        $this->assertSame('current-live-photo', $image->streamedContent());
    }

    public function test_new_checkin_clears_previous_visitor_state_but_keeps_admin_signed_in(): void
    {
        $response = $this->withSession([
            'admin_authenticated' => true,
            'admin_username' => 'admin',
            'verification' => ['verification_id' => 'old-verification'],
            'didit_verification' => ['verification_id' => 'old-didit'],
            'visitor_registration' => ['record_id' => 99],
            'visitor_category' => ['name' => 'Old category'],
        ])->get(route('visitor.start'));

        $response->assertRedirect(route('visitor.create'));
        $response->assertSessionHas('admin_authenticated', true);
        $response->assertSessionMissing('verification');
        $response->assertSessionMissing('didit_verification');
        $response->assertSessionMissing('visitor_registration');
        $response->assertSessionMissing('visitor_category');
    }

    public function test_admin_can_delete_a_visitor_and_all_stored_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        foreach (['front.jpg', 'back.jpg', 'live.jpg'] as $file) {
            Storage::disk('local')->put('verified-visitors/'.$file, $file);
        }

        $visitor = VerifiedVisitor::create([
            'verification_id' => '53d4d58d-412f-4c48-91c3-1352038913fa',
            'full_name' => 'Delete Visitor',
            'photo_path' => 'verified-visitors/front.jpg',
            'back_photo_path' => 'verified-visitors/back.jpg',
            'selfie_path' => 'verified-visitors/live.jpg',
            'payment_status' => 'paid',
            'face_verification_status' => 'verified',
            'is_blocked' => false,
        ]);
        $relatedOrphan = 'verified-visitors/'.$visitor->verification_id.'-retry-live.webp';
        $legacyRelated = 'verified-visitors/'.$visitor->id.'_legacy-photo.jpg';
        $unrelated = 'verified-visitors/'.$visitor->id.'0-other-visitor.jpg';
        Storage::disk('local')->put($relatedOrphan, 'related orphan');
        Storage::disk('public')->put($legacyRelated, 'legacy public copy');
        Storage::disk('local')->put($unrelated, 'must remain');

        $response = $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->delete(route('admin.visitors.destroy', $visitor));

        $response->assertRedirect(route('admin.visitors.index'));
        $this->assertDatabaseMissing('verified_visitors', ['id' => $visitor->id]);
        Storage::disk('local')->assertMissing([
            'verified-visitors/front.jpg',
            'verified-visitors/back.jpg',
            'verified-visitors/live.jpg',
            $relatedOrphan,
        ]);
        Storage::disk('public')->assertMissing($legacyRelated);
        Storage::disk('local')->assertExists($unrelated);
    }

    public function test_admin_visitor_search_payment_and_location_filters_use_current_records(): void
    {
        $inside = VerifiedVisitor::create([
            'verification_id' => '63d4d58d-412f-4c48-91c3-1352038913fa',
            'full_name' => 'Alpha Inside Visitor',
            'document_number' => '991111111V',
            'payment_status' => 'paid',
            'face_verification_status' => 'verified',
            'is_blocked' => false,
        ]);
        $outside = VerifiedVisitor::create([
            'verification_id' => '73d4d58d-412f-4c48-91c3-1352038913fa',
            'full_name' => 'Beta Outside Visitor',
            'document_number' => '992222222V',
            'payment_status' => 'pending',
            'face_verification_status' => 'pending',
            'is_blocked' => false,
        ]);
        GateLog::create([
            'visitor_id' => $inside->id,
            'gate' => 'ADMIN',
            'direction' => 'in',
            'scanned_at' => now(),
        ]);
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)->get(route('admin.visitors.index', [
            'search' => 'Alpha',
            'payment_status' => 'paid',
            'checkin_status' => 'inside',
        ]))
            ->assertOk()
            ->assertSee('Alpha Inside Visitor')
            ->assertDontSee('Beta Outside Visitor');

        $this->withSession($session)->get(route('admin.visitors.index', [
            'checkin_status' => 'outside',
        ]))
            ->assertOk()
            ->assertSee('Beta Outside Visitor')
            ->assertDontSee('Alpha Inside Visitor');
    }

    public function test_admin_checkin_control_records_in_and_out_movements(): void
    {
        Carbon::setTestNow('2026-07-28 11:00:00');
        $visitor = VerifiedVisitor::create([
            'verification_id' => '83d4d58d-412f-4c48-91c3-1352038913fa',
            'full_name' => 'Movement Visitor',
            'payment_status' => 'paid',
            'face_verification_status' => 'verified',
            'is_blocked' => false,
        ]);
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->patch(route('admin.visitors.checkin', $visitor))
            ->assertRedirect(route('admin.visitors.index'));
        $this->assertSame('in', $visitor->gateLogs()->latest('id')->value('direction'));

        Carbon::setTestNow('2026-07-28 11:00:10');
        $this->withSession($session)
            ->patch(route('admin.visitors.checkin', $visitor))
            ->assertRedirect(route('admin.visitors.index'));
        $this->assertSame('out', $visitor->gateLogs()->latest('id')->value('direction'));
        Carbon::setTestNow();
    }

    public function test_attendance_section_is_available_to_an_authorized_admin(): void
    {
        $session = [
            'admin_authenticated' => true,
            'admin_username' => 'admin',
            'admin_permissions' => ['Dashboard', 'Attendance Summary', 'Attendance Detail'],
        ];

        $this->withSession($session)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Attendance<', false)
            ->assertSee('Attendance Summary');

        $this->withSession($session)
            ->get(route('admin.attendance.summary'))
            ->assertOk()
            ->assertSee('Attendance Summary');

        $this->withSession($session)
            ->get(route('admin.attendance.detail_with_photo'))
            ->assertOk()
            ->assertSee('Attendance Detail with Photos');
    }
}
