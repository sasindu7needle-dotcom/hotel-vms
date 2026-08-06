<?php

namespace Tests\Feature;

use App\Models\EventConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_the_single_event_configuration(): void
    {
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->put(route('admin.configurations.event.update'), [
                'event_name' => 'Tech Expo 2026',
                'event_location' => 'BMICH, Colombo',
                'starts_on' => '2026-08-10',
                'ends_on' => '2026-08-12',
                'organized_by' => 'Needle Innovations',
            ])
            ->assertRedirect(route('admin.configurations.event.edit'))
            ->assertSessionHas('status', 'Event configuration saved successfully.');

        $configuration = EventConfiguration::firstOrFail();
        $this->assertTrue($configuration->is_active);
        $this->assertSame(1000, $configuration->capacity_limit);

        $this->withSession($session)
            ->put(route('admin.configurations.event.update'), [
                'event_name' => 'Tech Expo 2026 — Updated',
                'event_location' => 'SLECC, Colombo',
                'starts_on' => '2026-08-11',
                'ends_on' => '2026-08-13',
                'organized_by' => 'Needle Innovations',
            ])
            ->assertRedirect(route('admin.configurations.event.edit'));

        $this->assertDatabaseCount('event_configurations', 1);
        $this->assertDatabaseHas('event_configurations', [
            'id' => $configuration->id,
            'event_name' => 'Tech Expo 2026 — Updated',
            'event_location' => 'SLECC, Colombo',
            'capacity_limit' => 1000,
        ]);
    }

    public function test_event_end_date_cannot_be_before_start_date(): void
    {
        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->from(route('admin.configurations.event.edit'))
            ->put(route('admin.configurations.event.update'), [
                'event_name' => 'Tech Expo 2026',
                'event_location' => 'BMICH, Colombo',
                'starts_on' => '2026-08-12',
                'ends_on' => '2026-08-10',
                'organized_by' => 'Needle Innovations',
            ])
            ->assertRedirect(route('admin.configurations.event.edit'))
            ->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount('event_configurations', 0);
    }

    public function test_admin_can_remove_the_saved_event_configuration(): void
    {
        $configuration = EventConfiguration::create([
            'singleton_key' => EventConfiguration::SINGLETON_KEY,
            'event_name' => 'Tech Expo 2026',
            'event_location' => 'BMICH, Colombo',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-12',
            'organized_by' => 'Needle Innovations',
            'is_active' => true,
        ]);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->delete(route('admin.configurations.event.destroy'))
            ->assertRedirect(route('admin.configurations.event.edit'))
            ->assertSessionHas('status', 'Event configuration removed successfully.');

        $this->assertDatabaseMissing('event_configurations', ['id' => $configuration->id]);
    }
}
