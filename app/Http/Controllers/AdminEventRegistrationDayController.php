<?php

namespace App\Http\Controllers;

use App\Models\EventConfiguration;
use App\Models\EventRegistrationDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\CarbonPeriod;

class AdminEventRegistrationDayController extends Controller
{
    public function generate(Request $request): RedirectResponse
    {
        $event = $this->activeEvent();
        $validated = $request->validate([
            'entrance_fee' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
        ]);

        foreach (CarbonPeriod::create($event->starts_on, $event->ends_on) as $index => $date) {
            $event->registrationDays()->firstOrCreate(
                ['event_date' => $date->format('Y-m-d')],
                [
                    'label' => 'Registration for Day '.($index + 1),
                    'entrance_fee' => $validated['entrance_fee'],
                    'is_active' => true,
                ],
            );
        }

        return back()->with('status', 'A registration form is ready for every event day.');
    }

    public function store(Request $request): RedirectResponse
    {
        $event = $this->activeEvent();
        $validated = $this->validateDay($request, $event);

        $event->registrationDays()->create($validated);

        return back()->with('status', 'Daily registration form created successfully.');
    }

    public function update(Request $request, EventRegistrationDay $registrationDay): RedirectResponse
    {
        $event = $this->activeEvent();
        abort_unless($registrationDay->event_configuration_id === $event->id, 404);

        $registrationDay->update($this->validateDay($request, $event, $registrationDay));

        return back()->with('status', 'Daily registration form updated successfully.');
    }

    public function toggle(EventRegistrationDay $registrationDay): RedirectResponse
    {
        abort_unless($registrationDay->event_configuration_id === $this->activeEvent()->id, 404);
        $registrationDay->update(['is_active' => ! $registrationDay->is_active]);

        return back()->with('status', $registrationDay->is_active
            ? 'Daily registration form opened.'
            : 'Daily registration form closed.');
    }

    public function destroy(EventRegistrationDay $registrationDay): RedirectResponse
    {
        abort_unless($registrationDay->event_configuration_id === $this->activeEvent()->id, 404);

        if ($registrationDay->visitors()->exists()) {
            return back()->withErrors([
                'registration_day' => 'This form already has registrations and cannot be deleted. Close it instead.',
            ]);
        }

        $registrationDay->delete();

        return back()->with('status', 'Daily registration form removed.');
    }

    private function activeEvent(): EventConfiguration
    {
        return EventConfiguration::where('singleton_key', EventConfiguration::SINGLETON_KEY)->firstOrFail();
    }

    private function validateDay(
        Request $request,
        EventConfiguration $event,
        ?EventRegistrationDay $registrationDay = null
    ): array {
        return $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'event_date' => [
                'required',
                'date',
                'after_or_equal:'.$event->starts_on->format('Y-m-d'),
                'before_or_equal:'.$event->ends_on->format('Y-m-d'),
                Rule::unique('event_registration_days', 'event_date')
                    ->where('event_configuration_id', $event->id)
                    ->ignore($registrationDay?->id),
            ],
            'entrance_fee' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
