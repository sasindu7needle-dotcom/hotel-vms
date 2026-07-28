<?php

namespace App\Http\Controllers;

use App\Models\EventConfiguration;
use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCapacityController extends Controller
{
    public function edit(): View
    {
        return view('admin.configurations.capacity', [
            'eventConfiguration' => EventConfiguration::where(
                'singleton_key',
                EventConfiguration::SINGLETON_KEY
            )->first(),
            'insideCount' => $this->insideCount(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'capacity_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);
        $configuration = EventConfiguration::where(
            'singleton_key',
            EventConfiguration::SINGLETON_KEY
        )->first();

        if (! $configuration) {
            return redirect()
                ->route('admin.configurations.event.edit')
                ->withErrors([
                    'event_name' => 'Create the active event before setting its occupancy limit.',
                ]);
        }

        $insideCount = $this->insideCount();
        if ($validated['capacity_limit'] < $insideCount) {
            return back()
                ->withInput()
                ->withErrors([
                    'capacity_limit' => "The occupancy limit cannot be below the {$insideCount} visitors currently inside.",
                ]);
        }

        $configuration->update([
            'capacity_limit' => $validated['capacity_limit'],
        ]);

        return redirect()
            ->route('admin.configurations.capacity.edit')
            ->with('status', 'Event occupancy limit updated successfully.');
    }

    private function insideCount(): int
    {
        $latestLogIds = GateLog::query()->selectRaw('MAX(id)')->groupBy('visitor_id');

        return VerifiedVisitor::query()
            ->whereHas('gateLogs', fn ($query) => $query
                ->whereIn('id', $latestLogIds)
                ->where('direction', 'in'))
            ->count();
    }
}
