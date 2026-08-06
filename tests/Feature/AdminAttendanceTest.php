<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_summary_respects_date_gate_and_movement_filters(): void
    {
        $visitor = VerifiedVisitor::create([
            'verification_id' => 'attendance-summary-visitor',
            'full_name' => 'Attendance Visitor',
            'payment_status' => 'paid',
            'is_blocked' => false,
        ]);
        GateLog::create(['visitor_id' => $visitor->id, 'gate' => 'A', 'direction' => 'in', 'scanned_at' => Carbon::parse('2026-07-30 08:56:00')]);
        GateLog::create(['visitor_id' => $visitor->id, 'gate' => 'C', 'direction' => 'out', 'scanned_at' => Carbon::parse('2026-07-30 10:00:00')]);

        $this->withSession($this->summarySession())
            ->get(route('admin.attendance.summary', ['date_from' => '2026-07-30', 'date_to' => '2026-07-30', 'gate' => 'A', 'direction' => 'in']))
            ->assertOk()
            ->assertSee('30/07/2026')
            ->assertSee('0000 : 2359')
            ->assertSee('>1<', false)
            ->assertDontSee('<td>C</td>', false);
    }

    public function test_detail_matches_a_later_checkout_to_its_entry(): void
    {
        $visitor = VerifiedVisitor::create([
            'verification_id' => 'attendance-detail-visitor',
            'full_name' => 'Detail Visitor',
            'document_number' => '199012345678',
            'payment_status' => 'paid',
            'is_blocked' => false,
        ]);
        GateLog::create(['visitor_id' => $visitor->id, 'gate' => 'A', 'direction' => 'in', 'scanned_at' => Carbon::parse('2026-07-30 08:56:00')]);
        GateLog::create(['visitor_id' => $visitor->id, 'gate' => 'C', 'direction' => 'out', 'scanned_at' => Carbon::parse('2026-07-30 10:00:00')]);

        $this->withSession($this->detailSession())
            ->get(route('admin.attendance.detail'))
            ->assertOk()
            ->assertSee('Detail Visitor')
            ->assertSee('08:56')
            ->assertSee('10:00')
            ->assertSee('1 hour 4 minutes');
    }

    public function test_summary_permission_does_not_grant_detailed_attendance_access(): void
    {
        $this->withSession($this->summarySession())
            ->get(route('admin.attendance.detail'))
            ->assertRedirect(route('admin.attendance.summary'))
            ->assertSessionHasErrors('access');
    }

    public function test_detailed_attendance_permission_can_load_only_the_captured_visitor_photo(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verified-visitors/attendance-selfie.jpg', 'attendance photo');
        $visitor = VerifiedVisitor::create([
            'verification_id' => 'attendance-photo-visitor',
            'full_name' => 'Photo Visitor',
            'payment_status' => 'paid',
            'is_blocked' => false,
            'selfie_path' => 'verified-visitors/attendance-selfie.jpg',
            'selfie_mime' => 'image/jpeg',
        ]);

        $this->withSession($this->detailSession())
            ->get(route('admin.visitors.selfie', $visitor))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    private function summarySession(): array
    {
        return ['admin_authenticated' => true, 'admin_username' => 'reports', 'admin_permissions' => ['Attendance Summary']];
    }

    private function detailSession(): array
    {
        return ['admin_authenticated' => true, 'admin_username' => 'reports', 'admin_permissions' => ['Attendance Detail']];
    }
}
