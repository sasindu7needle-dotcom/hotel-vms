@php($selectedRegistrationDay = session('event_registration_day', []))
@if(data_get($selectedRegistrationDay, 'event_date'))
    <div style="display:inline-flex;align-items:center;gap:7px;margin:10px 0 16px;padding:8px 11px;color:#52601f;background:#f3f8de;border:1px solid #dce8aa;border-radius:999px;font-size:10px;font-weight:800">
        <span>{{ data_get($selectedRegistrationDay, 'label') }}</span>
        <span>·</span>
        <span>{{ \Illuminate\Support\Carbon::parse(data_get($selectedRegistrationDay, 'event_date'))->format('d M Y') }}</span>
        <span>· LKR {{ number_format((float) data_get($selectedRegistrationDay, 'entrance_fee'), 2) }}</span>
    </div>
@endif
