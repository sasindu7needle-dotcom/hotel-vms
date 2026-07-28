<?php

namespace Tests\Feature;

use App\Models\EventConfiguration;
use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
