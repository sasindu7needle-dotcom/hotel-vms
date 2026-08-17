@php
    $editing = isset($schedule);
    $emailRecipients = $editing ? $schedule->recipients->where('channel', 'email')->pluck('address')->all() : [''];
    $smsRecipients = $editing ? $schedule->recipients->where('channel', 'sms')->pluck('address')->all() : [''];
    $selectedEmailReports = $editing ? $schedule->reports->where('channel', 'email')->pluck('report_type')->all() : [];
    $selectedSmsReports = $editing ? $schedule->reports->where('channel', 'sms')->pluck('report_type')->all() : [];
@endphp
<form method="POST" action="{{ $editing ? route('admin.configurations.schedules.update', $schedule) : route('admin.configurations.schedules.store') }}" class="report-schedule-form">
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="report-schedule-title-row">
        <label class="configuration-field"><span>Schedule name <b>*</b></span><input name="name" maxlength="120" required value="{{ old('name', $editing ? $schedule->name : '') }}" placeholder="e.g. Management Daily Reports"></label>
        @if($editing)<span class="report-schedule-state {{ $schedule->is_active ? 'active' : 'paused' }}">{{ $schedule->is_active ? 'Active' : 'Paused' }}</span>@endif
    </div>

    <div class="report-schedule-channels">
        <section class="report-channel" data-channel="email">
            <label class="report-channel-heading"><input type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $editing ? $schedule->email_enabled : false))><span><strong>Email delivery</strong><small>Detailed reports are delivered as CSV attachments.</small></span></label>
            <label class="configuration-field"><span>Send time</span><input type="time" name="email_time" value="{{ old('email_time', $editing ? substr((string) $schedule->email_time, 0, 5) : '22:00') }}"></label>
            <div class="report-recipient-block"><div><span>Email recipients</span><button type="button" class="report-add-recipient" data-kind="email">+ Add email</button></div><div class="report-recipient-list">@foreach($emailRecipients ?: [''] as $email)<label><input type="email" name="emails[]" value="{{ $email }}" placeholder="name@example.com"><button type="button" class="report-remove-recipient" aria-label="Remove email">×</button></label>@endforeach</div></div>
            <fieldset class="report-type-list"><legend>Reports to send</legend>@foreach($emailTypes as $type)<label><input type="checkbox" name="email_reports[]" value="{{ $type }}" @checked(in_array($type, old('email_reports', $selectedEmailReports), true))><span>{{ $reportTypes[$type] }}</span></label>@endforeach</fieldset>
        </section>

        <section class="report-channel" data-channel="sms">
            <label class="report-channel-heading"><input type="checkbox" name="sms_enabled" value="1" @checked(old('sms_enabled', $editing ? $schedule->sms_enabled : false))><span><strong>SMS delivery</strong><small>SMS sends compact summaries; no attachments.</small></span></label>
            <label class="configuration-field"><span>Send time</span><input type="time" name="sms_time" value="{{ old('sms_time', $editing ? substr((string) $schedule->sms_time, 0, 5) : '18:00') }}"></label>
            <div class="report-recipient-block"><div><span>Mobile recipients</span><button type="button" class="report-add-recipient" data-kind="sms">+ Add mobile</button></div><div class="report-recipient-list">@foreach($smsRecipients ?: [''] as $mobile)<label><input type="tel" name="mobiles[]" value="{{ $mobile }}" placeholder="e.g. +94771234567"><button type="button" class="report-remove-recipient" aria-label="Remove mobile">×</button></label>@endforeach</div></div>
            <fieldset class="report-type-list"><legend>Reports to send</legend>@foreach($smsTypes as $type)<label><input type="checkbox" name="sms_reports[]" value="{{ $type }}" @checked(in_array($type, old('sms_reports', $selectedSmsReports), true))><span>{{ $reportTypes[$type] }}</span></label>@endforeach</fieldset>
        </section>
    </div>
    <div class="configuration-actions report-schedule-actions"><p>Times use the application timezone: {{ config('app.timezone') }}. A delivery is audited once per channel and report date.</p><button class="btn btn-primary" type="submit">{{ $editing ? 'Save Schedule' : 'Create Schedule' }} <span>→</span></button></div>
</form>
