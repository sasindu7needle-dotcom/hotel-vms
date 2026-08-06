@extends('layouts.admin')

@section('title', $title)

@section('header')
    <div>
        <span class="tagline no-margin">ATTENDANCE REPORTING</span>
        <h1>{{ $title }}<span>.</span></h1>
        <p>{{ $description }}</p>
    </div>
@endsection

@section('content')
    @if($errors->any())
        <div class="admin-page-alert admin-attendance-alert" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <section class="admin-panel admin-attendance-panel">
        <div class="configuration-panel-heading">
            <div><span>REPORT FILTERS</span><h2>Filter attendance records</h2></div>
        </div>
        <form method="GET" class="admin-attendance-filters">
            <label>Date from<input type="date" name="date_from" value="{{ $filters['date_from'] }}"></label>
            <label>Date to<input type="date" name="date_to" value="{{ $filters['date_to'] }}"></label>
            <label>Time from<input type="time" name="time_from" value="{{ $filters['time_from'] }}"></label>
            <label>Time to<input type="time" name="time_to" value="{{ $filters['time_to'] }}"></label>
            <label>Gate<select name="gate"><option value="">All gates</option>@foreach($gates as $gate)<option value="{{ $gate }}" @selected($filters['gate'] === $gate)>{{ $gate }}</option>@endforeach</select></label>
            @if($report === 'summary')
                <label>Movement<select name="direction"><option value="">In and out</option><option value="in" @selected($filters['direction'] === 'in')>Entry only</option><option value="out" @selected($filters['direction'] === 'out')>Exit only</option></select></label>
            @endif
            @if($report !== 'summary')
                <label class="admin-attendance-search">Visitor search<input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="Name, NIC or mobile"></label>
            @endif
            <div class="admin-attendance-filter-actions"><button class="btn btn-primary" type="submit">Apply filters</button><a href="{{ url()->current() }}">Clear</a></div>
        </form>
    </section>

    <section class="admin-panel admin-attendance-panel admin-attendance-results">
        <div class="configuration-panel-heading"><div><span>{{ strtoupper(str_replace('-', ' ', $report)) }}</span><h2>{{ number_format($rows->total()) }} record{{ $rows->total() === 1 ? '' : 's' }}</h2></div></div>
        <div class="table-responsive">
            <table class="admin-table admin-attendance-table">
                @if($report === 'entries')
                    <thead><tr><th>#</th><th>Date</th><th>Time in</th><th>Gate in</th><th>NIC / Passport</th><th>Name</th><th>Detail</th></tr></thead>
                    <tbody>@forelse($rows as $log)<tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ $log->scanned_at->format('d/m/Y') }}</td><td>{{ $log->scanned_at->format('H:i') }}</td><td>{{ $log->gate }}</td><td>{{ $log->visitor?->document_number ?: '—' }}</td><td>{{ $log->visitor?->full_name ?: 'Deleted visitor' }}</td><td>@if($log->visitor)<a class="admin-attendance-view" href="{{ route('admin.visitors.index', ['search' => $log->visitor->document_number ?: $log->visitor->full_name]) }}">View</a>@endif</td></tr>@empty<tr><td colspan="7" class="admin-empty-state">No entry activity matches the selected filters.</td></tr>@endforelse</tbody>
                @elseif($report === 'summary')
                    <thead><tr><th>#</th><th>Date</th><th>Time Range</th><th>Gate</th><th>In / Out</th><th>Count</th></tr></thead>
                    <tbody>@forelse($rows as $row)<tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->attendance_date)->format('d/m/Y') }}</td><td>{{ $summaryTimeRange }}</td><td>{{ $row->gate }}</td><td><span class="admin-attendance-direction {{ $row->direction }}">{{ strtoupper($row->direction) }}</span></td><td>{{ number_format($row->attendance_count) }}</td></tr>@empty<tr><td colspan="6" class="admin-empty-state">No gate activity matches the selected filters.</td></tr>@endforelse</tbody>
                @else
                    <thead><tr><th>#</th><th>Date</th>@if($report === 'detail-photo')<th>Photo</th>@endif<th>Name</th><th>NIC / Passport</th><th>In</th><th>Out</th><th>Duration</th><th>Gate in</th><th>Gate out</th></tr></thead>
                    <tbody>@forelse($rows as $log)@php($checkout = $log->matched_checkout)<tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ $log->scanned_at->format('d/m/Y') }}</td>@if($report === 'detail-photo')<td><div class="admin-attendance-photo">@if($log->visitor?->selfie_path)<img src="{{ route('admin.visitors.selfie', ['visitor' => $log->visitor, 'v' => $log->visitor->updated_at?->format('Uu')]) }}" alt="Photo of {{ $log->visitor->full_name }}">@else<span>{{ mb_strtoupper(mb_substr($log->visitor?->full_name ?: '?', 0, 1)) }}</span>@endif</div></td>@endif<td>{{ $log->visitor?->full_name ?: 'Deleted visitor' }}</td><td>{{ $log->visitor?->document_number ?: '—' }}</td><td>{{ $log->scanned_at->format('H:i') }}</td><td>{{ $checkout?->scanned_at?->format('H:i') ?: '—' }}</td><td>{{ $checkout ? $log->scanned_at->diffForHumans($checkout->scanned_at, true, false, 2) : 'Inside now' }}</td><td>{{ $log->gate }}</td><td>{{ $checkout?->gate ?: '—' }}</td></tr>@empty<tr><td colspan="{{ $report === 'detail-photo' ? 10 : 9 }}" class="admin-empty-state">No entry activity matches the selected filters.</td></tr>@endforelse</tbody>
                @endif
            </table>
        </div>
        @if($rows->hasPages())<nav class="admin-pagination" aria-label="Attendance report pages"><span>Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</span><div class="admin-page-links">@if($rows->onFirstPage())<span class="disabled">Previous</span>@else<a href="{{ $rows->previousPageUrl() }}">Previous</a>@endif @if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}">Next</a>@else<span class="disabled">Next</span>@endif</div></nav>@endif
    </section>
@endsection

@push('styles')
<style>
body.landing-page .admin-attendance-panel{max-width:1240px}body.landing-page .admin-attendance-panel+.admin-attendance-panel{margin-top:20px}.admin-attendance-filters{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:14px;padding:20px 24px 24px}.admin-attendance-filters label{display:grid;gap:6px;color:#64748b;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.admin-attendance-filters input,.admin-attendance-filters select{box-sizing:border-box;width:100%;height:42px;padding:0 10px;color:#172033;background:#fff;border:1px solid #d8e0e7;border-radius:8px;font:600 12px Inter,sans-serif;outline:0}.admin-attendance-filters input:focus,.admin-attendance-filters select:focus{border-color:#a8bd38;box-shadow:0 0 0 3px rgba(200,224,99,.23)}.admin-attendance-search{grid-column:span 2}.admin-attendance-filter-actions{display:flex;align-items:end;gap:12px}.admin-attendance-filter-actions .btn{min-height:42px;white-space:nowrap}.admin-attendance-filter-actions a{padding:13px 2px;color:#5d700a;font-size:11px;font-weight:800;text-decoration:none}.admin-attendance-table td{vertical-align:middle}.admin-attendance-view{color:#536b00;font-size:11px;font-weight:800;text-decoration:none}.admin-attendance-view:hover{text-decoration:underline}.admin-attendance-direction{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:9px;font-weight:800;letter-spacing:.05em}.admin-attendance-direction.in{color:#526600;background:#f1f8d4}.admin-attendance-direction.out{color:#355f86;background:#eaf5ff}.admin-attendance-photo{display:grid;place-items:center;overflow:hidden;width:40px;height:40px;color:#536b00;background:#f1f7d5;border:1px solid #d8e59d;border-radius:50%;font-size:13px;font-weight:800}.admin-attendance-photo img{width:100%;height:100%;object-fit:cover}.admin-attendance-alert{margin-bottom:20px;padding:13px 17px;color:#991b1b;background:#fff1f1;border:1px solid #fecaca;border-radius:10px;font-size:12px;font-weight:600}@media(max-width:1050px){.admin-attendance-filters{grid-template-columns:repeat(3,minmax(150px,1fr))}.admin-attendance-search{grid-column:span 2}}@media(max-width:650px){.admin-attendance-filters{grid-template-columns:1fr;padding:18px}.admin-attendance-search{grid-column:auto}.admin-attendance-filter-actions{align-items:center}.admin-attendance-filter-actions .btn{flex:1}}
</style>
@endpush
