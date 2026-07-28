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
        return view('admin.configurations.event', [
            'eventConfiguration' => EventConfiguration::where('singleton_key', EventConfiguration::SINGLETON_KEY)->first(),
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

        EventConfiguration::updateOrCreate(
            ['singleton_key' => EventConfiguration::SINGLETON_KEY],
            $validated + ['is_active' => true],
        );

        return redirect()
            ->route('admin.configurations.event.edit')
            ->with('status', 'Event configuration saved successfully.');
    }
}
