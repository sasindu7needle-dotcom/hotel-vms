@extends('layouts.admin')

@section('title', $title)

@section('header')
    <div>
        <span class="tagline no-margin">REVENUE REPORTING</span>
        <h1>{{ $title }}<span>.</span></h1>
        <p>{{ $description }}</p>
    </div>
@endsection

@section('content')
    @if($errors->any())
        <div class="admin-page-alert admin-revenue-alert" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <section class="admin-panel admin-revenue-panel">
        <div class="configuration-panel-heading"><div><span>REPORT FILTERS</span><h2>Filter confirmed payments</h2></div></div>
        <form method="GET" class="admin-revenue-filters">
            <label>Date from<input type="date" name="date_from" value="{{ $filters['date_from'] }}"></label>
            <label>Date to<input type="date" name="date_to" value="{{ $filters['date_to'] }}"></label>
            <label>Time from<input type="time" name="time_from" value="{{ $filters['time_from'] }}"></label>
            <label>Time to<input type="time" name="time_to" value="{{ $filters['time_to'] }}"></label>
            <label>Gate in<select name="gate"><option value="">All gates</option>@foreach($gates as $gate)<option value="{{ $gate }}" @selected($filters['gate'] === $gate)>{{ $gate }}</option>@endforeach</select></label>
            <label>Payment method<select name="payment_method"><option value="">All methods</option><option value="cash" @selected($filters['payment_method'] === 'cash')>Cash</option><option value="visa_master" @selected($filters['payment_method'] === 'visa_master')>Visa / MasterCard</option><option value="amex" @selected($filters['payment_method'] === 'amex')>American Express</option></select></label>
            <div class="admin-revenue-filter-actions"><button class="btn btn-primary" type="submit">Apply filters</button><a href="{{ url()->current() }}">Clear</a></div>
        </form>
    </section>

    <section class="admin-panel admin-revenue-panel admin-revenue-results">
        <div class="configuration-panel-heading"><div><span>{{ strtoupper($report) }}</span><h2>{{ number_format($rows->total()) }} payment{{ $rows->total() === 1 ? '' : 's' }}</h2></div></div>
        <div class="table-responsive">
            <table class="admin-table admin-revenue-table">
                @if($report === 'summary')
                    <thead><tr><th>#</th><th>Date</th><th>Time Range</th><th>Gate</th><th>Method</th><th>Revenue (LKR)</th></tr></thead>
                    <tbody>@forelse($rows as $row)<tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->payment_date)->format('d/m/Y') }}</td><td>{{ $summaryTimeRange }}</td><td>{{ $row->gate ?: 'Not checked in' }}</td><td>{{ match($row->payment_method) { 'visa_master' => 'Visa / MasterCard', 'amex' => 'American Express', 'cash' => 'Cash', default => 'Not recorded' } }}</td><td class="admin-revenue-amount">{{ number_format((float) $row->revenue_total, 2) }}</td></tr>@empty<tr><td colspan="6" class="admin-empty-state">No confirmed payments match the selected filters.</td></tr>@endforelse</tbody>
                @else
                    <thead><tr><th>#</th><th>Date</th><th>Name</th><th>NIC / Passport</th><th>Paid Amount (LKR)</th><th>Method</th><th>Gate in</th></tr></thead>
                    <tbody>@forelse($rows as $visitor)<tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ ($visitor->paid_at ?: $visitor->updated_at)->format('d/m/Y H:i') }}</td><td>{{ $visitor->full_name ?: 'Unnamed visitor' }}</td><td>{{ $visitor->document_number ?: '—' }}</td><td class="admin-revenue-amount">{{ number_format((float) $visitor->entrance_fee, 2) }}</td><td>{{ match($visitor->payment_method) { 'visa_master' => 'Visa / MasterCard', 'amex' => 'American Express', 'cash' => 'Cash', default => 'Not recorded' } }}</td><td>{{ $visitor->entry_gate ?: 'Not checked in' }}</td></tr>@empty<tr><td colspan="7" class="admin-empty-state">No confirmed payments match the selected filters.</td></tr>@endforelse</tbody>
                @endif
            </table>
        </div>
        @if($rows->hasPages())<nav class="admin-pagination" aria-label="Revenue report pages"><span>Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</span><div class="admin-page-links">@if($rows->onFirstPage())<span class="disabled">Previous</span>@else<a href="{{ $rows->previousPageUrl() }}">Previous</a>@endif @if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}">Next</a>@else<span class="disabled">Next</span>@endif</div></nav>@endif
    </section>
@endsection

@push('styles')
<style>
body.landing-page .admin-revenue-panel{max-width:1240px}body.landing-page .admin-revenue-panel+.admin-revenue-panel{margin-top:20px}.admin-revenue-filters{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:14px;padding:20px 24px 24px}.admin-revenue-filters label{display:grid;gap:6px;color:#64748b;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.admin-revenue-filters input,.admin-revenue-filters select{box-sizing:border-box;width:100%;height:42px;padding:0 10px;color:#172033;background:#fff;border:1px solid #d8e0e7;border-radius:8px;font:600 12px Inter,sans-serif;outline:0}.admin-revenue-filters input:focus,.admin-revenue-filters select:focus{border-color:#a8bd38;box-shadow:0 0 0 3px rgba(200,224,99,.23)}.admin-revenue-filter-actions{display:flex;align-items:end;gap:12px}.admin-revenue-filter-actions .btn{min-height:42px;white-space:nowrap}.admin-revenue-filter-actions a{padding:13px 2px;color:#5d700a;font-size:11px;font-weight:800;text-decoration:none}.admin-revenue-table td{vertical-align:middle}.admin-revenue-amount{color:#3f5700;font-weight:800;font-variant-numeric:tabular-nums}.admin-revenue-alert{margin-bottom:20px;padding:13px 17px;color:#991b1b;background:#fff1f1;border:1px solid #fecaca;border-radius:10px;font-size:12px;font-weight:600}@media(max-width:1050px){.admin-revenue-filters{grid-template-columns:repeat(3,minmax(150px,1fr))}}@media(max-width:650px){.admin-revenue-filters{grid-template-columns:1fr;padding:18px}.admin-revenue-filter-actions{align-items:center}.admin-revenue-filter-actions .btn{flex:1}}
</style>
@endpush
