<?php

namespace App\Http\Controllers;

use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRevenueController extends Controller
{
    public function summary(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $paymentAt = 'COALESCE(verified_visitors.paid_at, verified_visitors.updated_at)';

        $rows = $this->paidVisitorsQuery($filters)
            ->selectRaw("DATE({$paymentAt}) as payment_date, first_gate_logs.gate as gate, verified_visitors.payment_method, SUM(verified_visitors.entrance_fee) as revenue_total")
            ->groupByRaw("DATE({$paymentAt}), first_gate_logs.gate, verified_visitors.payment_method")
            ->orderByDesc('payment_date')
            ->orderBy('gate')
            ->orderBy('payment_method')
            ->paginate(25)
            ->withQueryString();

        return view('admin.revenue.index', [
            'report' => 'summary',
            'title' => 'Revenue Summary',
            'description' => 'Paid entrance fees grouped by payment date, entry gate, and method.',
            'filters' => $filters,
            'summaryTimeRange' => $this->summaryTimeRange($filters),
            'gates' => $this->gates(),
            'rows' => $rows,
        ]);
    }

    public function detail(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $paymentAt = 'COALESCE(verified_visitors.paid_at, verified_visitors.updated_at)';

        $rows = $this->paidVisitorsQuery($filters)
            ->select('verified_visitors.*', 'first_gate_logs.gate as entry_gate')
            ->orderByDesc(DB::raw($paymentAt))
            ->orderByDesc('verified_visitors.id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.revenue.index', [
            'report' => 'detail',
            'title' => 'Revenue Detail',
            'description' => 'A read-only list of each confirmed entrance-fee payment.',
            'filters' => $filters,
            'gates' => $this->gates(),
            'rows' => $rows,
        ]);
    }

    /** @return array{date_from:?string,date_to:?string,time_from:?string,time_to:?string,gate:?string,payment_method:?string} */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', 'after_or_equal:time_from'],
            'gate' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['nullable', 'in:cash,visa_master,amex'],
        ]);

        return array_map(static fn ($value) => is_string($value) ? trim($value) : $value, array_merge([
            'date_from' => null, 'date_to' => null, 'time_from' => null,
            'time_to' => null, 'gate' => null, 'payment_method' => null,
        ], $filters));
    }

    private function paidVisitorsQuery(array $filters): Builder
    {
        $firstEntries = GateLog::query()
            ->selectRaw('visitor_id, MIN(id) as id')
            ->where('direction', 'in')
            ->groupBy('visitor_id');
        $paymentAt = DB::raw('COALESCE(verified_visitors.paid_at, verified_visitors.updated_at)');

        return VerifiedVisitor::query()
            ->leftJoinSub($firstEntries, 'first_entries', fn ($join) => $join->on('verified_visitors.id', '=', 'first_entries.visitor_id'))
            ->leftJoin('gate_logs as first_gate_logs', 'first_entries.id', '=', 'first_gate_logs.id')
            ->where('verified_visitors.payment_status', 'paid')
            ->whereNotNull('verified_visitors.entrance_fee')
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->whereDate($paymentAt, '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->whereDate($paymentAt, '<=', $date))
            ->when($filters['time_from'], fn (Builder $query, string $time) => $query->whereTime($paymentAt, '>=', $time))
            ->when($filters['time_to'], fn (Builder $query, string $time) => $query->whereTime($paymentAt, '<=', $time))
            ->when($filters['gate'], fn (Builder $query, string $gate) => $query->where('first_gate_logs.gate', $gate))
            ->when($filters['payment_method'], fn (Builder $query, string $method) => $query->where('verified_visitors.payment_method', $method));
    }

    /** @return Collection<int, string> */
    private function gates(): Collection
    {
        return GateLog::query()->where('direction', 'in')->whereNotNull('gate')->distinct()->orderBy('gate')->pluck('gate');
    }

    private function summaryTimeRange(array $filters): string
    {
        return str_replace(':', '', $filters['time_from'] ?: '00:00').' : '.str_replace(':', '', $filters['time_to'] ?: '23:59');
    }
}
