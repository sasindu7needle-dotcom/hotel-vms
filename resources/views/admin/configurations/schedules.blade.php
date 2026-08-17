@extends('layouts.admin')

@section('title', 'Schedule Manager')

@section('header')
    <div><span class="tagline no-margin">MASTER CONFIGURATIONS</span><h1>Schedule Manager<span>.</span></h1><p>Send the selected daily visitor and revenue reports to approved email and SMS recipients.</p></div>
@endsection

@section('content')
    <style>
        /* Kept with the page content so these layout rules are available even when the shared view stack is cached. */
        body.landing-page .report-schedule-panel > .report-schedule-form .report-schedule-title-row { margin: 26px 28px 0; padding: 0; }
        body.landing-page .report-schedule-panel > .report-schedule-form .report-schedule-channels { gap: 22px; padding: 22px 28px 28px; }
        body.landing-page .report-schedule-panel > .report-schedule-form .report-channel { padding: 22px; border-radius: 12px; }
        body.landing-page .report-schedule-panel > .report-schedule-form .report-schedule-actions { margin: 0 28px; padding: 20px 0 26px; }
        body.landing-page .report-schedule-list-panel .report-schedule-card { margin: 22px 28px; border-radius: 12px; }
        body.landing-page .report-schedule-list-panel .report-schedule-card-header { padding: 20px 22px; }
        body.landing-page .report-schedule-list-panel .report-schedule-channel-summaries { gap: 14px; padding: 20px 22px; }
        body.landing-page .report-schedule-list-panel .report-schedule-card-footer { padding: 14px 22px; }
        body.landing-page .report-schedule-list-panel .report-schedule-editor summary { padding: 16px 22px; }
        @media (max-width: 760px) {
            body.landing-page .report-schedule-panel > .report-schedule-form .report-schedule-title-row { margin: 18px 16px 0; }
            body.landing-page .report-schedule-panel > .report-schedule-form .report-schedule-channels { padding: 18px 16px 22px; }
            body.landing-page .report-schedule-panel > .report-schedule-form .report-schedule-actions { margin: 0 16px; }
            body.landing-page .report-schedule-list-panel .report-schedule-card { margin: 18px 16px; }
        }
    </style>
    @if(session('status'))<div class="admin-page-alert configuration-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="admin-page-alert report-schedule-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="admin-panel configuration-panel report-schedule-panel">
        <div class="configuration-panel-heading"><div><span>NEW DAILY SCHEDULE</span><h2>Choose recipients, reports and delivery time</h2><p>Email supports detailed report attachments. SMS is designed for fast operational summaries.</p></div></div>
        @include('admin.configurations.partials.schedule-form')
    </section>

    <section class="admin-panel configuration-panel report-schedule-panel report-schedule-list-panel">
        <div class="configuration-panel-heading"><div><span>ACTIVE CONFIGURATION</span><h2>Saved schedules</h2><p>Pause a schedule without losing its recipients and report choices.</p></div><span class="configuration-active-badge"><i></i> {{ $schedules->where('is_active')->count() }} Active</span></div>
        @forelse($schedules as $schedule)
            <article class="report-schedule-card">
                @php
                    $delivery = $schedule->deliveries->first();
                    $emailRecipients = $schedule->recipients->where('channel', 'email')->count();
                    $smsRecipients = $schedule->recipients->where('channel', 'sms')->count();
                    $emailReports = $schedule->reports->where('channel', 'email')->count();
                    $smsReports = $schedule->reports->where('channel', 'sms')->count();
                @endphp
                <div class="report-schedule-card-header">
                    <div class="report-schedule-card-title"><span class="report-schedule-card-label">Daily report schedule</span><h3>{{ $schedule->name }}</h3></div>
                    <div class="report-schedule-card-actions">
                        <span class="report-schedule-state {{ $schedule->is_active ? 'active' : 'paused' }}">{{ $schedule->is_active ? 'Active' : 'Paused' }}</span>
                        <form method="POST" action="{{ route('admin.configurations.schedules.toggle', $schedule) }}">@csrf @method('PATCH')<button type="submit" class="report-schedule-secondary-action">{{ $schedule->is_active ? 'Pause' : 'Activate' }}</button></form>
                        <form method="POST" action="{{ route('admin.configurations.schedules.destroy', $schedule) }}" onsubmit="return confirm('Remove this daily report schedule? Its delivery history will also be removed.');">@csrf @method('DELETE')<button type="submit" class="report-schedule-remove-action">Remove</button></form>
                    </div>
                </div>
                <div class="report-schedule-channel-summaries">
                    @if($schedule->email_enabled)
                        <div class="report-schedule-channel-summary"><span class="report-schedule-channel-icon email" aria-hidden="true">@</span><div><strong>Email delivery</strong><span>Daily at {{ \Illuminate\Support\Carbon::parse($schedule->email_time)->format('g:i A') }}</span></div><em>{{ $emailRecipients }} {{ \Illuminate\Support\Str::plural('recipient', $emailRecipients) }} &middot; {{ $emailReports }} {{ \Illuminate\Support\Str::plural('report', $emailReports) }}</em></div>
                    @endif
                    @if($schedule->sms_enabled)
                        <div class="report-schedule-channel-summary"><span class="report-schedule-channel-icon sms" aria-hidden="true">#</span><div><strong>SMS delivery</strong><span>Daily at {{ \Illuminate\Support\Carbon::parse($schedule->sms_time)->format('g:i A') }}</span></div><em>{{ $smsRecipients }} {{ \Illuminate\Support\Str::plural('recipient', $smsRecipients) }} &middot; {{ $smsReports }} {{ \Illuminate\Support\Str::plural('report', $smsReports) }}</em></div>
                    @endif
                </div>
                <div class="report-schedule-card-footer">
                    <div><strong>Latest delivery</strong><span>{{ $delivery ? ucfirst($delivery->channel).' | '.ucfirst($delivery->status).' | '.$delivery->created_at->format('d M H:i') : 'No delivery attempts yet' }}</span></div>
                </div>
                <details class="report-schedule-editor">
                    <summary><span>Edit schedule configuration</span><i aria-hidden="true"></i></summary>
                    <div class="report-schedule-editor-content">@include('admin.configurations.partials.schedule-form', ['schedule' => $schedule])</div>
                </details>
            </article>
        @empty
            <div class="report-schedule-empty">No schedules have been created. Add the first daily delivery above.</div>
        @endforelse
    </section>
@endsection

@push('styles')
<style>
.report-schedule-panel{max-width:1240px}.report-schedule-list-panel{margin-top:28px}.report-schedule-title-row{display:flex;align-items:end;gap:14px;padding:24px 28px 0}.report-schedule-title-row .configuration-field{flex:1}.report-schedule-state{display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border-radius:99px;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}.report-schedule-state.active{color:#3f5700;background:#eff9c9}.report-schedule-state.paused{color:#7c3f00;background:#fff0d6}.report-schedule-channels{display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:24px 28px}.report-channel{padding:20px;border:1px solid #e1e6e9;border-radius:12px;background:#fbfcfa}.report-channel-heading{display:flex;align-items:flex-start;gap:10px;margin-bottom:20px;cursor:pointer}.report-channel-heading input{width:18px;height:18px;margin:1px 0 0;accent-color:#819717}.report-channel-heading strong,.report-channel-heading small{display:block}.report-channel-heading strong{font-size:13px;color:#172033}.report-channel-heading small{margin-top:3px;color:#718096;font-size:10px;line-height:1.4}.report-channel .configuration-field>span,.report-recipient-block>div>span,.report-type-list legend{color:#64748b;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.report-channel .configuration-field input{width:100%;height:42px;margin-top:7px;box-sizing:border-box;border:1px solid #d8e0e7;border-radius:8px;padding:0 10px;font:600 12px Inter,sans-serif}.report-recipient-block{margin-top:20px}.report-recipient-block>div:first-child{display:flex;justify-content:space-between;align-items:center}.report-add-recipient{padding:0;border:0;background:none;color:#66800b;font-size:11px;font-weight:800;cursor:pointer}.report-recipient-list{display:grid;gap:8px;margin-top:8px}.report-recipient-list label{display:flex;gap:7px}.report-recipient-list input{min-width:0;flex:1;height:38px;border:1px solid #d8e0e7;border-radius:7px;padding:0 9px;font:500 12px Inter,sans-serif}.report-remove-recipient{width:36px;border:1px solid #f5c6c6;border-radius:7px;color:#c02b35;background:#fff7f7;font-size:18px;cursor:pointer}.report-type-list{display:grid;gap:9px;margin:20px 0 0;padding:15px;border:1px solid #e1e6e9;border-radius:8px}.report-type-list legend{padding:0 3px}.report-type-list label{display:flex;align-items:center;gap:8px;color:#334155;font-size:12px;font-weight:600;cursor:pointer}.report-type-list input{width:15px;height:15px;accent-color:#819717}.report-schedule-actions{margin:0 28px;padding:18px 0 24px}.report-schedule-actions p{max-width:580px}.report-schedule-card{margin:0 24px 20px;border:1px solid #dfe6cf;border-radius:12px;background:#fff;overflow:hidden;box-shadow:0 2px 8px rgba(40,55,30,.04)}.report-schedule-card-header{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:20px 20px 18px;background:#f7faef;border-bottom:1px solid #e3ebd2}.report-schedule-card-label{display:block;color:#71800f;font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.report-schedule-card-title h3{margin:5px 0 0;color:#172033;font-size:17px;line-height:1.2}.report-schedule-card-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px}.report-schedule-card-actions form{margin:0}.report-schedule-secondary-action,.report-schedule-remove-action{padding:7px 10px;border-radius:7px;background:#fff;font-size:11px;font-weight:700;cursor:pointer}.report-schedule-secondary-action{border:1px solid #dce4b5;color:#5a6d08}.report-schedule-remove-action{border:1px solid #f1c8c8;color:#a1262d}.report-schedule-channel-summaries{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:18px 20px}.report-schedule-channel-summary{display:grid;grid-template-columns:30px minmax(0,1fr);column-gap:10px;align-items:center;padding:12px;border:1px solid #e5e9ed;border-radius:9px;background:#fbfcfd}.report-schedule-channel-icon{display:inline-flex;grid-row:span 2;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;font-weight:800}.report-schedule-channel-icon.email{color:#526c08;background:#eff8d1}.report-schedule-channel-icon.sms{color:#315f93;background:#e7f2ff}.report-schedule-channel-summary strong,.report-schedule-channel-summary span{display:block}.report-schedule-channel-summary strong{color:#233044;font-size:12px}.report-schedule-channel-summary div>span{margin-top:3px;color:#64748b;font-size:10px}.report-schedule-channel-summary em{grid-column:2;color:#7b8794;font-size:10px;font-style:normal}.report-schedule-card-footer{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:12px 20px;background:#f8fafc;border-top:1px solid #e5e7eb}.report-schedule-card-footer strong,.report-schedule-card-footer span{display:block}.report-schedule-card-footer strong{font-size:9px;text-transform:uppercase;letter-spacing:.07em;color:#64748b}.report-schedule-card-footer span{margin-top:3px;font-size:11px;color:#334155}.report-schedule-editor{border-top:1px solid #e5e7eb}.report-schedule-editor summary{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;color:#61750b;font-size:11px;font-weight:800;cursor:pointer;list-style:none}.report-schedule-editor summary::-webkit-details-marker{display:none}.report-schedule-editor summary i{width:8px;height:8px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg) translateY(-2px);transition:transform .15s ease}.report-schedule-editor[open] summary{background:#fafcf5;border-bottom:1px solid #e5e7eb}.report-schedule-editor[open] summary i{transform:rotate(225deg) translate(-2px,-2px)}.report-schedule-editor-content{background:#fff}.report-schedule-editor .report-schedule-title-row{background:#fff}.report-schedule-empty{margin:0 24px 24px;padding:38px;text-align:center;color:#7c8997;font-size:12px}.report-schedule-error{color:#991b1b;background:#fff1f1;border-color:#fecaca}.report-schedule-error ul{margin:0;padding-left:18px}@media(max-width:760px){.report-schedule-channels,.report-schedule-channel-summaries{grid-template-columns:1fr;padding:16px}.report-schedule-title-row{padding:18px 16px 0}.report-schedule-actions{margin:0 16px}.report-schedule-card{margin:0 16px 16px}.report-schedule-card-header{align-items:flex-start;flex-direction:column}.report-schedule-card-actions{width:100%;justify-content:flex-start;flex-wrap:wrap}.report-schedule-card-footer{align-items:flex-start;flex-direction:column}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('click', event => {
    const add = event.target.closest('.report-add-recipient');
    if (add) {
        const channel = add.closest('.report-channel'); const list = channel.querySelector('.report-recipient-list'); const sms = add.dataset.kind === 'sms';
        const row = document.createElement('label'); row.innerHTML = `<input type="${sms ? 'tel' : 'email'}" name="${sms ? 'mobiles[]' : 'emails[]'}" placeholder="${sms ? 'e.g. +94771234567' : 'name@example.com'}"><button type="button" class="report-remove-recipient" aria-label="Remove recipient">×</button>`; list.append(row); row.querySelector('input').focus();
    }
    const remove = event.target.closest('.report-remove-recipient');
    if (remove) { const list = remove.closest('.report-recipient-list'); if (list.children.length > 1) remove.closest('label').remove(); else remove.closest('label').querySelector('input').value = ''; }
});
</script>
@endpush
