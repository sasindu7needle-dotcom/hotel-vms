<?php

namespace Tests\Feature;

use App\Exceptions\GateScanException;
use App\Models\EventConfiguration;
use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use App\Services\GateLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class GateLogTest extends TestCase
{
    use RefreshDatabase;

    private GateLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow();
        $this->service = app(GateLogService::class);
    }

    public function test_first_scan_checks_in_and_next_movement_checks_out(): void
    {
        Carbon::setTestNow('2026-07-27 09:14:00');
        $visitor = $this->visitor();

        $in = $this->service->scan($visitor->verification_id, 'a');
        $this->assertSame('in', $in->direction);
        $this->assertSame('A', $in->gate);
        $this->assertTrue($visitor->fresh()->checkin_status);

        Carbon::setTestNow('2026-07-27 09:15:00');
        $out = $this->service->scan($visitor->verification_id, 'B');
        $this->assertSame('out', $out->direction);
        $this->assertFalse($visitor->fresh()->checkin_status);
    }

    public function test_invalid_blocked_and_immediate_replay_scans_are_rejected(): void
    {
        try {
            $this->service->scan('unknown-code', 'A');
            $this->fail('Unknown QR should fail.');
        } catch (GateScanException $exception) {
            $this->assertSame('not_found', $exception->reason);
        }

        $blocked = $this->visitor(['is_blocked' => true]);
        try {
            $this->service->scan($blocked->verification_id, 'A');
            $this->fail('Blocked visitor should fail.');
        } catch (GateScanException $exception) {
            $this->assertSame('blocked', $exception->reason);
        }

        $visitor = $this->visitor();
        $this->service->scan($visitor->verification_id, 'A');
        $this->expectException(GateScanException::class);
        $this->expectExceptionMessage('Duplicate scan ignored');
        $this->service->scan($visitor->verification_id, 'A');
    }

    public function test_fixed_direction_terminals_reject_invalid_movement_sequences(): void
    {
        $visitor = $this->visitor();

        try {
            $this->service->scan($visitor->verification_id, 'A', null, 'out');
            $this->fail('An OUT terminal must reject a visitor who is not inside.');
        } catch (GateScanException $exception) {
            $this->assertSame('out_without_in', $exception->reason);
        }

        $this->assertSame('in', $this->service->scan($visitor->verification_id, 'A', null, 'in')->direction);

        try {
            $this->service->scan($visitor->verification_id, 'A', null, 'in');
            $this->fail('An IN terminal must reject a second check-in.');
        } catch (GateScanException $exception) {
            $this->assertSame('duplicate_in', $exception->reason);
        }

        $this->assertSame('out', $this->service->scan($visitor->verification_id, 'B', null, 'out')->direction);
    }

    public function test_event_capacity_is_enforced_for_gate_and_admin_checkins(): void
    {
        EventConfiguration::create([
            'singleton_key' => EventConfiguration::SINGLETON_KEY,
            'event_name' => 'Full Event',
            'event_location' => 'Colombo',
            'starts_on' => '2026-07-28',
            'ends_on' => '2026-07-30',
            'organized_by' => 'Needle',
            'capacity_limit' => 1,
            'is_active' => true,
        ]);
        $inside = $this->visitor();
        $waiting = $this->visitor();

        $this->service->scan($inside->verification_id, 'A', null, 'in');

        try {
            $this->service->preview($waiting->verification_id, 'in');
            $this->fail('A gate preview must reject a check-in when capacity is full.');
        } catch (GateScanException $exception) {
            $this->assertSame('capacity_reached', $exception->reason);
            $this->assertSame(409, $exception->status);
        }

        try {
            $this->service->scan($waiting->verification_id, 'ADMIN', null, 'in');
            $this->fail('An admin check-in must reject a check-in when capacity is full.');
        } catch (GateScanException $exception) {
            $this->assertSame('capacity_reached', $exception->reason);
        }

        $this->assertDatabaseCount('gate_logs', 1);
    }

    public function test_gate_in_and_out_pages_use_standalone_urls(): void
    {
        $this->get('/gate/A/in')
            ->assertOk()
            ->assertSee('IN TERMINAL')
            ->assertSee('Accept &amp; check IN', false)
            ->assertSee('Reject')
            ->assertDontSee('admin-sidebar');

        $this->get('/gate/A/out')
            ->assertOk()
            ->assertSee('OUT TERMINAL')
            ->assertDontSee('admin-sidebar');

        $this->get('/gate/B/in')->assertNotFound();
        $this->get('/gate/C/out')->assertNotFound();
        $this->get('/gate/D/in')->assertNotFound();
    }

    public function test_gate_scan_requires_guard_confirmation_before_recording_movement(): void
    {
        $visitor = $this->visitor([
            'document_type' => 'national_id',
            'document_number' => '991234567V',
            'company' => 'Needle',
            'category' => 'Guest',
        ]);

        $preview = $this->postJson('/gate/A/in', [
            'qr_value' => $visitor->verification_id,
            'gate' => 'A',
            'direction' => 'in',
            'action' => 'preview',
        ]);

        $preview->assertOk()
            ->assertJson([
                'ok' => true,
                'requires_confirmation' => true,
                'visitor' => [
                    'name' => 'Test Visitor',
                    'category' => 'Guest',
                    'document_type' => 'NATIONAL ID',
                    'document_number' => '991234567V',
                    'company' => 'Needle',
                ],
            ]);
        $this->assertDatabaseCount('gate_logs', 0);
        $this->assertFalse($visitor->fresh()->checkin_status);

        $accepted = $this->postJson('/gate/A/in', [
            'qr_value' => $visitor->verification_id,
            'gate' => 'A',
            'direction' => 'in',
            'action' => 'accept',
        ]);

        $accepted->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('movement.direction', 'in');
        $this->assertDatabaseCount('gate_logs', 1);
        $this->assertTrue($visitor->fresh()->checkin_status);
    }

    public function test_activity_pairs_in_and_out_and_keeps_open_visit(): void
    {
        $visitor = $this->visitor();
        $logs = collect([
            new GateLog(['direction' => 'in', 'gate' => 'A', 'scanned_at' => Carbon::parse('2026-07-27 09:00')]),
            new GateLog(['direction' => 'out', 'gate' => 'B', 'scanned_at' => Carbon::parse('2026-07-27 10:35')]),
            new GateLog(['direction' => 'in', 'gate' => 'C', 'scanned_at' => Carbon::parse('2026-07-27 11:00')]),
        ]);
        $logs->each(fn ($log, $index) => $log->id = $index + 1);

        $rows = $this->service->activityRows($logs);

        $this->assertCount(2, $rows);
        $this->assertNull($rows[0]['out']);
        $this->assertSame(95, $rows[1]['duration_minutes']);
        $this->assertSame('B', $rows[1]['out']->gate);
    }

    public function test_dashboard_counts_only_visitors_whose_latest_log_is_in(): void
    {
        $inside = $this->visitor(['category' => 'Exhibitor']);
        $outside = $this->visitor(['category' => 'Staff']);
        GateLog::create(['visitor_id' => $inside->id, 'gate' => 'A', 'direction' => 'in', 'scanned_at' => now()]);
        GateLog::create(['visitor_id' => $outside->id, 'gate' => 'A', 'direction' => 'in', 'scanned_at' => now()->subMinute()]);
        GateLog::create(['visitor_id' => $outside->id, 'gate' => 'B', 'direction' => 'out', 'scanned_at' => now()]);

        $response = $this->withSession(['admin_authenticated' => true])->getJson(route('admin.dashboard.counts'));

        $response->assertOk()->assertJson([
            'inside' => 1,
            'visitor' => 0,
            'exhibitor' => 1,
            'staff' => 0,
        ]);
    }

    private function visitor(array $attributes = []): VerifiedVisitor
    {
        return VerifiedVisitor::create(array_merge([
            'verification_id' => (string) Str::uuid(),
            'full_name' => 'Test Visitor',
            'registration_status' => 'registered',
            'payment_status' => 'paid',
        ], $attributes));
    }
}
