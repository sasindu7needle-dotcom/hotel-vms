<?php

namespace Tests\Feature;

use App\Models\EventConfiguration;
use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use App\Models\VisitorCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_adjust_inside_count_and_movements_remain_auditable(): void
    {
        $this->event(3);
        $visitors = collect([
            $this->visitor('Visitor One'),
            $this->visitor('Visitor Two'),
            $this->visitor('Visitor Three'),
        ]);
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('EVENT OCCUPANCY CONTROL')
            ->assertSee('Receipt Manager')
            ->assertSee('Update Count');

        $this->withSession($session)
            ->patch(route('admin.dashboard.inside_count'), ['inside_count' => 2])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status');

        $this->assertSame(2, GateLog::where('direction', 'in')->count());
        $this->assertSame(2, $visitors->filter(fn ($visitor) => $visitor->fresh()->checkin_status)->count());

        $this->withSession($session)
            ->patch(route('admin.dashboard.inside_count'), ['inside_count' => 1])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status');

        $latestLogIds = GateLog::query()->selectRaw('MAX(id)')->groupBy('visitor_id');
        $insideCount = VerifiedVisitor::query()
            ->whereHas('gateLogs', fn ($query) => $query
                ->whereIn('id', $latestLogIds)
                ->where('direction', 'in'))
            ->count();

        $this->assertSame(1, $insideCount);
        $this->assertSame(1, GateLog::where('direction', 'out')->count());
    }

    public function test_dashboard_adjustment_cannot_exceed_capacity_or_available_visitors(): void
    {
        $this->event(2);
        $this->visitor('Only Eligible Visitor');
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->from(route('admin.dashboard'))
            ->patch(route('admin.dashboard.inside_count'), ['inside_count' => 3])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('inside_count');
        $this->assertDatabaseCount('gate_logs', 0);

        $this->withSession($session)
            ->from(route('admin.dashboard'))
            ->patch(route('admin.dashboard.inside_count'), ['inside_count' => 2])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('inside_count');
        $this->assertDatabaseCount('gate_logs', 0);
    }

    public function test_dashboard_displays_profile_photos_for_people_currently_inside(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verified-visitors/live-inside.jpg', 'profile image');
        $participant = $this->visitor('Inside With Photo');
        $participant->update([
            'category' => 'Staff',
            'selfie_path' => 'verified-visitors/live-inside.jpg',
            'selfie_mime' => 'image/jpeg',
        ]);
        GateLog::create([
            'visitor_id' => $participant->id,
            'gate' => 'A',
            'direction' => 'in',
            'scanned_at' => now(),
        ]);
        $session = [
            'admin_authenticated' => true,
            'admin_username' => 'dashboard',
            'admin_permissions' => ['Dashboard'],
        ];

        $this->withSession($session)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Participants currently at the event')
            ->assertSee('Inside With Photo')
            ->assertSee(route('admin.visitors.selfie', $participant), false);

        $this->withSession($session)
            ->get(route('admin.visitors.selfie', $participant))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_live_profile_includes_person_details_and_gate_activity_for_the_modal(): void
    {
        $participant = $this->visitor('Modal Participant');
        $participant->update([
            'category' => 'Staff',
            'document_number' => '901234567V',
            'mobile_number' => '0771234567',
            'company' => 'Needle Events',
            'occupation' => 'Coordinator',
        ]);
        GateLog::create([
            'visitor_id' => $participant->id,
            'gate' => 'A',
            'direction' => 'in',
            'scanned_at' => now()->subHours(3),
        ]);
        GateLog::create([
            'visitor_id' => $participant->id,
            'gate' => 'B',
            'direction' => 'out',
            'scanned_at' => now()->subHours(2),
        ]);
        GateLog::create([
            'visitor_id' => $participant->id,
            'gate' => 'C',
            'direction' => 'in',
            'scanned_at' => now()->subMinutes(30),
        ]);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'dashboard'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-live-profile=', false)
            ->assertSee('Modal Participant')
            ->assertSee('901234567V')
            ->assertSee('Check-in and check-out history');
    }

    public function test_dashboard_adds_a_section_for_each_configured_visitor_category(): void
    {
        $category = VisitorCategory::create([
            'name' => 'Sponsors',
            'code' => 'sponsors',
            'badge_color' => '#c8e063',
            'entrance_fee' => 0,
            'is_active' => true,
        ]);
        $participant = $this->visitor('Sponsor Inside');
        $participant->update(['visitor_category_id' => $category->id, 'category' => 'Sponsors']);
        GateLog::create([
            'visitor_id' => $participant->id,
            'gate' => 'A',
            'direction' => 'in',
            'scanned_at' => now(),
        ]);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'dashboard'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Sponsors')
            ->assertSee('Sponsor Inside')
            ->assertSee('data-live-category-count="category-'.$category->id.'"', false);
    }

    private function event(int $capacity): EventConfiguration
    {
        return EventConfiguration::create([
            'singleton_key' => EventConfiguration::SINGLETON_KEY,
            'event_name' => 'Capacity Test Event',
            'event_location' => 'Colombo',
            'starts_on' => '2026-07-28',
            'ends_on' => '2026-07-30',
            'organized_by' => 'Needle',
            'capacity_limit' => $capacity,
            'is_active' => true,
        ]);
    }

    private function visitor(string $name): VerifiedVisitor
    {
        return VerifiedVisitor::create([
            'verification_id' => (string) Str::uuid(),
            'full_name' => $name,
            'registration_status' => 'registered',
            'payment_status' => 'paid',
            'face_verification_status' => 'verified',
            'is_blocked' => false,
            'verified_at' => now(),
        ]);
    }
}
