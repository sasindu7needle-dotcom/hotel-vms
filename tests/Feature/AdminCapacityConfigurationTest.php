<?php

namespace Tests\Feature;

use App\Models\EventConfiguration;
use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCapacityConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_occupancy_limit_has_a_separate_admin_page_and_can_be_updated(): void
    {
        $configuration = $this->event(100);
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->get(route('admin.configurations.capacity.edit'))
            ->assertOk()
            ->assertSee('Occupancy Limit')
            ->assertSee('Maximum Visitors Inside')
            ->assertSee('value="100"', false);

        $this->withSession($session)
            ->put(route('admin.configurations.capacity.update'), [
                'capacity_limit' => 250,
            ])
            ->assertRedirect(route('admin.configurations.capacity.edit'))
            ->assertSessionHas('status', 'Event occupancy limit updated successfully.');

        $this->assertSame(250, $configuration->fresh()->capacity_limit);
    }

    public function test_occupancy_limit_cannot_be_reduced_below_current_inside_count(): void
    {
        $configuration = $this->event(5);
        foreach (range(1, 2) as $number) {
            $visitor = VerifiedVisitor::create([
                'verification_id' => (string) Str::uuid(),
                'full_name' => "Inside Visitor {$number}",
                'payment_status' => 'paid',
            ]);
            GateLog::create([
                'visitor_id' => $visitor->id,
                'gate' => 'A',
                'direction' => 'in',
                'scanned_at' => now(),
            ]);
        }

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->from(route('admin.configurations.capacity.edit'))
            ->put(route('admin.configurations.capacity.update'), [
                'capacity_limit' => 1,
            ])
            ->assertRedirect(route('admin.configurations.capacity.edit'))
            ->assertSessionHasErrors('capacity_limit');

        $this->assertSame(5, $configuration->fresh()->capacity_limit);
    }

    private function event(int $capacity): EventConfiguration
    {
        return EventConfiguration::create([
            'singleton_key' => EventConfiguration::SINGLETON_KEY,
            'event_name' => 'Capacity Event',
            'event_location' => 'Colombo',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-12',
            'organized_by' => 'Needle',
            'capacity_limit' => $capacity,
            'is_active' => true,
        ]);
    }
}
