<?php

namespace App\Http\Controllers;

use App\Exceptions\GateScanException;
use App\Models\EventConfiguration;
use App\Models\GateLog;
use App\Models\User;
use App\Models\VerifiedVisitor;
use App\Models\VisitorCategory;
use App\Services\GateLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $liveCounts = $this->liveCounts();
        $insideCategories = $this->insideCategories();
        $stats = [
            'total' => VerifiedVisitor::count(),
            'today' => VerifiedVisitor::whereDate('verified_at', today())->count(),
            'checked_in' => $liveCounts['inside'],
            'checked_out' => GateLog::where('direction', 'out')->whereDate('scanned_at', today())->count(),
            'visitors_inside' => $liveCounts['visitor'],
            'exhibitors_inside' => $liveCounts['exhibitor'],
            'staff_inside' => $liveCounts['staff'],
        ];

        $recentVisitors = VerifiedVisitor::with(['gateLogs' => fn ($query) => $query->latest('scanned_at')->latest('id')])
            ->latest()
            ->limit(8)
            ->get();
        $eventConfiguration = EventConfiguration::where(
            'singleton_key',
            EventConfiguration::SINGLETON_KEY
        )->first();

        return view('admin.dashboard', compact(
            'stats',
            'insideCategories',
            'recentVisitors',
            'eventConfiguration'
        ));
    }

    public function counts(): JsonResponse
    {
        $counts = $this->liveCounts();
        $counts['categories'] = collect($this->insideCategories())
            ->mapWithKeys(fn (array $group) => [$group['key'] => $group['participants']->count()]);

        return response()->json($counts);
    }

    public function updateInsideCount(Request $request, GateLogService $gateLogService): RedirectResponse
    {
        $validated = $request->validate([
            'inside_count' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $configuration = EventConfiguration::where(
            'singleton_key',
            EventConfiguration::SINGLETON_KEY
        )->first();

        if (! $configuration) {
            return back()->withErrors([
                'inside_count' => 'Set the event capacity in Event Configurations before adjusting this count.',
            ]);
        }

        $target = (int) $validated['inside_count'];
        if ($target > $configuration->capacity_limit) {
            return back()->withInput()->withErrors([
                'inside_count' => "Currently inside cannot exceed the event capacity of {$configuration->capacity_limit}.",
            ]);
        }

        $insideIds = $this->insideVisitorQuery()->pluck('id');
        $current = $insideIds->count();
        $difference = abs($target - $current);

        if ($difference === 0) {
            return back()->with('status', "Currently inside is already {$target}.");
        }

        $visitors = $target < $current
            ? VerifiedVisitor::query()
                ->whereIn('id', $insideIds)
                ->latest('checked_in_at')
                ->latest('id')
                ->limit($difference)
                ->get()
            : VerifiedVisitor::query()
                ->whereNotIn('id', $insideIds)
                ->where('is_blocked', false)
                ->latest('verified_at')
                ->latest('id')
                ->limit($difference)
                ->get();

        if ($visitors->count() < $difference) {
            return back()->withInput()->withErrors([
                'inside_count' => "The count cannot be raised to {$target}; only {$visitors->count()} eligible checked-out visitors are available.",
            ]);
        }

        $adminUsername = (string) $request->session()->get('admin_username');
        $scannedBy = auth()->id() ?: User::query()
            ->where('name', $adminUsername)
            ->orWhere('email', $adminUsername)
            ->value('id');
        $direction = $target > $current ? 'in' : 'out';

        try {
            DB::transaction(function () use ($visitors, $direction, $scannedBy, $gateLogService) {
                foreach ($visitors as $visitor) {
                    $gateLogService->scan(
                        (string) ($visitor->verification_id ?: $visitor->id),
                        'ADMIN',
                        $scannedBy,
                        $direction
                    );
                }
            }, 3);
        } catch (GateScanException $exception) {
            return back()->withInput()->withErrors([
                'inside_count' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('status', "Currently inside adjusted from {$current} to {$target}. Gate activity was recorded.");
    }

    private function liveCounts(): array
    {
        $inside = $this->insideVisitorQuery();

        $categories = (clone $inside)
            ->selectRaw("LOWER(COALESCE(category, 'visitor')) as category_name, COUNT(*) as total")
            ->groupByRaw("LOWER(COALESCE(category, 'visitor'))")
            ->pluck('total', 'category_name');

        $insideTotal = (clone $inside)->count();
        $exhibitors = (int) $categories->filter(fn ($total, $category) => str_contains($category, 'exhibitor'))->sum();
        $staff = (int) $categories->filter(fn ($total, $category) => str_contains($category, 'staff'))->sum();

        return [
            'inside' => $insideTotal,
            'visitor' => max(0, $insideTotal - $exhibitors - $staff),
            'exhibitor' => $exhibitors,
            'staff' => $staff,
            'capacity_limit' => EventConfiguration::where(
                'singleton_key',
                EventConfiguration::SINGLETON_KEY
            )->value('capacity_limit'),
        ];
    }

    /**
     * Group people whose latest gate activity is an entry. The photo roster
     * and the existing live counters therefore always use the same source.
     */
    private function insideCategories(): array
    {
        $configuredCategories = VisitorCategory::query()->orderBy('name')->get();
        $groups = [
            'visitor' => ['key' => 'visitor', 'label' => 'Visitors', 'participants' => collect()],
            'exhibitor' => ['key' => 'exhibitor', 'label' => 'Exhibitors', 'participants' => collect()],
            'staff' => ['key' => 'staff', 'label' => 'Staff', 'participants' => collect()],
        ];

        $categoryGroupKeys = [];
        foreach ($configuredCategories as $category) {
            $normalisedName = strtolower($category->name);
            $fallbackGroup = str_contains($normalisedName, 'exhibitor')
                ? 'exhibitor'
                : (str_contains($normalisedName, 'staff') ? 'staff' : null);
            $groupKey = $fallbackGroup ?: 'category-'.$category->id;

            if (! $fallbackGroup) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' => $category->name,
                    'participants' => collect(),
                ];
            }

            $categoryGroupKeys[$category->id] = $groupKey;
        }

        $this->insideVisitorQuery()
            ->with('visitorCategory')
            ->latest('checked_in_at')
            ->latest('id')
            ->get()
            ->each(function (VerifiedVisitor $participant) use (&$groups, $categoryGroupKeys) {
                $linkedGroup = $participant->visitor_category_id
                    ? ($categoryGroupKeys[$participant->visitor_category_id] ?? null)
                    : null;
                $category = strtolower((string) $participant->category);
                $group = $linkedGroup ?: (str_contains($category, 'exhibitor')
                    ? 'exhibitor'
                    : (str_contains($category, 'staff') ? 'staff' : 'visitor'));

                $groups[$group]['participants']->push($participant);
            });

        return $groups;
    }

    private function insideVisitorQuery()
    {
        $latestLogIds = GateLog::query()->selectRaw('MAX(id)')->groupBy('visitor_id');

        return VerifiedVisitor::query()
            ->whereHas('gateLogs', fn ($query) => $query
                ->whereIn('id', $latestLogIds)
                ->where('direction', 'in'));
    }
}
