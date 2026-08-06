<?php

namespace App\Http\Controllers;

use App\Models\GateLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminAttendanceController extends Controller
{
    public function entries(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $logs = $this->filteredLogs($filters, false)
            ->where('direction', 'in')
            ->with('visitor:id,full_name,document_number')
            ->latest('scanned_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.attendance.index', [
            'report' => 'entries',
            'title' => 'Attendance Register',
            'description' => 'Every recorded entry, with the visitor identity and entry gate.',
            'filters' => $filters,
            'gates' => $this->gates(),
            'rows' => $logs,
        ]);
    }

    public function summary(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $rows = $this->filteredLogs($filters)
            ->selectRaw('DATE(scanned_at) as attendance_date, gate, direction, COUNT(*) as attendance_count')
            ->groupByRaw('DATE(scanned_at), gate, direction')
            ->orderByDesc('attendance_date')
            ->orderBy('gate')
            ->orderBy('direction')
            ->paginate(25)
            ->withQueryString();

        return view('admin.attendance.index', [
            'report' => 'summary',
            'title' => 'Attendance Summary',
            'description' => 'A count of entries and exits by date and gate.',
            'filters' => $filters,
            'summaryTimeRange' => $this->summaryTimeRange($filters),
            'gates' => $this->gates(),
            'rows' => $rows,
        ]);
    }

    public function detail(Request $request): View
    {
        return $this->detailReport($request, false);
    }

    public function detailWithPhoto(Request $request): View
    {
        return $this->detailReport($request, true);
    }

    private function detailReport(Request $request, bool $withPhotos): View
    {
        $filters = $this->validatedFilters($request);
        $entries = $this->filteredLogs($filters, false)
            ->where('direction', 'in')
            ->with('visitor:id,full_name,document_number,selfie_path,updated_at')
            ->latest('scanned_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $this->attachCheckouts($entries);

        return view('admin.attendance.index', [
            'report' => $withPhotos ? 'detail-photo' : 'detail',
            'title' => $withPhotos ? 'Attendance Detail with Photos' : 'Attendance Detail',
            'description' => 'Entry and exit times are matched from the immutable gate activity log.',
            'filters' => $filters,
            'gates' => $this->gates(),
            'rows' => $entries,
        ]);
    }

    /**
     * @return array{date_from:?string,date_to:?string,time_from:?string,time_to:?string,gate:?string,direction:?string,search:?string}
     */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', 'after_or_equal:time_from'],
            'gate' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'in:in,out'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return array_map(
            static fn ($value) => is_string($value) ? trim($value) : $value,
            array_merge([
                'date_from' => null, 'date_to' => null, 'time_from' => null,
                'time_to' => null, 'gate' => null, 'direction' => null, 'search' => null,
            ], $filters)
        );
    }

    private function filteredLogs(array $filters, bool $includeDirection = true): Builder
    {
        return GateLog::query()
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->whereDate('scanned_at', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->whereDate('scanned_at', '<=', $date))
            ->when($filters['time_from'], fn (Builder $query, string $time) => $query->whereTime('scanned_at', '>=', $time))
            ->when($filters['time_to'], fn (Builder $query, string $time) => $query->whereTime('scanned_at', '<=', $time))
            ->when($filters['gate'], fn (Builder $query, string $gate) => $query->where('gate', $gate))
            ->when($includeDirection ? $filters['direction'] : null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when($filters['search'], function (Builder $query, string $search) {
                $query->whereHas('visitor', function (Builder $visitorQuery) use ($search) {
                    $visitorQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            });
    }

    /** @return Collection<int, string> */
    private function gates(): Collection
    {
        return GateLog::query()->whereNotNull('gate')->distinct()->orderBy('gate')->pluck('gate');
    }

    private function summaryTimeRange(array $filters): string
    {
        $from = str_replace(':', '', $filters['time_from'] ?: '00:00');
        $to = str_replace(':', '', $filters['time_to'] ?: '23:59');

        return "{$from} : {$to}";
    }

    private function attachCheckouts(LengthAwarePaginator $entries): void
    {
        $entryRows = $entries->getCollection();
        if ($entryRows->isEmpty()) {
            return;
        }

        $firstEntryAt = $entryRows->min('scanned_at');
        $checkouts = GateLog::query()
            ->whereIn('visitor_id', $entryRows->pluck('visitor_id')->unique())
            ->where('direction', 'out')
            ->where('scanned_at', '>=', $firstEntryAt)
            ->orderBy('visitor_id')
            ->orderBy('scanned_at')
            ->orderBy('id')
            ->get()
            ->groupBy('visitor_id');

        $entryRows->each(function (GateLog $entry) use ($checkouts) {
            $checkout = ($checkouts->get($entry->visitor_id) ?? collect())
                ->first(fn (GateLog $candidate) => $candidate->scanned_at->greaterThan($entry->scanned_at));

            $entry->setAttribute('matched_checkout', $checkout);
        });
    }
}
