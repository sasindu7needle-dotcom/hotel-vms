<?php

namespace Tests\Feature;

use App\Models\EventConfiguration;
use App\Models\EventRegistrationDay;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyVisitorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_generate_a_separate_registration_form_for_every_event_date(): void
    {
        $event = $this->event();

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->post(route('admin.configurations.event.days.generate'), ['entrance_fee' => '1500.00'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('event_registration_days', 3);
        $this->assertDatabaseHas('event_registration_days', [
            'event_configuration_id' => $event->id,
            'label' => 'Registration for Day 1',
            'entrance_fee' => '1500.00',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('event_registration_days', [
            'label' => 'Registration for Day 3',
        ]);
        $this->assertSame(
            ['2026-08-10', '2026-08-11', '2026-08-12'],
            EventRegistrationDay::orderBy('event_date')->get()
                ->map(fn (EventRegistrationDay $day) => $day->event_date->format('Y-m-d'))
                ->all()
        );
    }

    public function test_same_person_can_register_and_pay_independently_for_different_days(): void
    {
        Carbon::setTestNow('2026-08-09 10:00:00');
        $event = $this->event();
        $days = collect([
            $event->registrationDays()->create(['label' => 'Registration for Day 1', 'event_date' => '2026-08-10', 'entrance_fee' => 1000, 'is_active' => true]),
            $event->registrationDays()->create(['label' => 'Registration for Day 2', 'event_date' => '2026-08-11', 'entrance_fee' => 1250, 'is_active' => true]),
        ]);

        $this->get(route('visitor.start'))->assertRedirect(route('visitor.registration-days'));
        $this->get(route('visitor.registration-days'))
            ->assertOk()
            ->assertSee('Registration for Day 1')
            ->assertSee('Registration for Day 2');

        foreach ($days as $index => $day) {
            $verificationId = '11111111-2222-4333-8444-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT);
            $verification = [
                'session_id' => $verificationId,
                'full_name' => 'Repeat Visitor',
                'document_number' => '199012345678',
                'address' => '12 Galle Road, Colombo',
                'selfie_path' => 'verified-visitors/repeat-photo.jpg',
            ];

            $this->post(route('visitor.registration-days.select'), ['registration_day_id' => $day->id])
                ->assertRedirect(route('visitor.create'));

            $this->withSession(['verification' => $verification, 'visitor_category' => ['name' => 'Adult', 'entrance_fee' => 99]])
                ->post(route('visitor.confirm'), [
                    'document_type' => 'nic',
                    'name_confirmation' => '1',
                    'full_name' => 'Repeat Visitor',
                    'document_number' => '199012345678',
                    'address' => '12 Galle Road, Colombo',
                    'mobile_number' => '771234567',
                    'same_as_mobile' => '1',
                    'occupation' => 'Engineer',
                    'company' => 'Example Ltd',
                ])->assertOk();

            $this->post(route('visitor.payment-method'), ['payment_method' => 'visa_master'])
                ->assertRedirect(route('visitor.payment.card'));
            $this->post(route('visitor.payment.confirm'))
                ->assertRedirect(route('visitor.thank-you'));
        }

        $this->assertSame(2, VerifiedVisitor::where('document_number', '199012345678')->count());
        $this->assertDatabaseHas('verified_visitors', [
            'document_number' => '199012345678',
            'event_registration_day_id' => $days[0]->id,
            'entrance_fee' => '1000.00',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('verified_visitors', [
            'document_number' => '199012345678',
            'event_registration_day_id' => $days[1]->id,
            'entrance_fee' => '1250.00',
            'payment_status' => 'paid',
        ]);
    }

    private function event(): EventConfiguration
    {
        return EventConfiguration::create([
            'singleton_key' => EventConfiguration::SINGLETON_KEY,
            'event_name' => 'Daily Expo',
            'event_location' => 'BMICH',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-12',
            'organized_by' => 'Needle',
            'is_active' => true,
        ]);
    }
}
