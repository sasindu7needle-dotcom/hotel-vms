<?php

namespace App\Http\Controllers;

use App\Models\EventConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminEventConfigurationController extends Controller
{
    public function edit(): View
    {
        $eventConfiguration = EventConfiguration::where('singleton_key', EventConfiguration::SINGLETON_KEY)
            ->with(['registrationDays' => fn ($query) => $query->withCount('visitors')])
            ->first();

        return view('admin.configurations.event', [
            'eventConfiguration' => $eventConfiguration,
            'registrationDays' => $eventConfiguration?->registrationDays ?? collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'event_location' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'organized_by' => ['required', 'string', 'max:255'],
        ]);

        $existing = EventConfiguration::where('singleton_key', EventConfiguration::SINGLETON_KEY)->first();
        if ($existing && $existing->registrationDays()
            ->where(function ($query) use ($validated) {
                $query->whereDate('event_date', '<', $validated['starts_on'])
                    ->orWhereDate('event_date', '>', $validated['ends_on']);
            })
            ->exists()) {
            return back()->withInput()->withErrors([
                'ends_on' => 'Update or remove daily registration forms outside the new event period first.',
            ]);
        }

        EventConfiguration::updateOrCreate(
            ['singleton_key' => EventConfiguration::SINGLETON_KEY],
            $validated + ['is_active' => true],
        );

        return redirect()
            ->route('admin.configurations.event.edit')
            ->with('status', 'Event configuration saved successfully.');
    }

    public function destroy(): RedirectResponse
    {
        $eventConfiguration = EventConfiguration::where('singleton_key', EventConfiguration::SINGLETON_KEY)->first();

        if (! $eventConfiguration) {
            return redirect()
                ->route('admin.configurations.event.edit')
                ->with('status', 'There is no event configuration to remove.');
        }

        if ($eventConfiguration->registrationDays()->whereHas('visitors')->exists()) {
            return redirect()
                ->route('admin.configurations.event.edit')
                ->withErrors([
                    'event' => 'This event has daily visitor registrations and cannot be removed.',
                ]);
        }

        $eventConfiguration->delete();

        return redirect()
            ->route('admin.configurations.event.edit')
            ->with('status', 'Event configuration removed successfully.');
    }
}
