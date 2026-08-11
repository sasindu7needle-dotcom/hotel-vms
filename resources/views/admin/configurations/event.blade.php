@extends('layouts.admin')

@section('title', 'Event Configurations')

@section('header')
    <div>
        <span class="tagline no-margin">MASTER CONFIGURATIONS</span>
        <h1>Event Configurations<span>.</span></h1>
        <p>Set the active event details used throughout visitor management</p>
    </div>
@endsection

@section('content')

    @if(session('status'))
        <div class="admin-page-alert configuration-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
            {{ session('status') }}
        </div>
    @endif

    @error('registration_day')
        <div class="admin-page-alert" style="color:#9f252e;background:#fff0f1;border-color:#efc3c6">{{ $message }}</div>
    @enderror
    @error('event')
        <div class="admin-page-alert" style="color:#9f252e;background:#fff0f1;border-color:#efc3c6">{{ $message }}</div>
    @enderror

    <section class="admin-panel configuration-panel">
        <div class="configuration-panel-heading">
            <div>
                <span>ACTIVE EVENT</span>
                <h2>{{ $eventConfiguration ? 'Update event details' : 'Create event details' }}</h2>
                <p>These details define the single event currently active in the system.</p>
            </div>
            @if($eventConfiguration)
                <span class="configuration-active-badge"><i></i> Active configuration</span>
            @endif
        </div>

        <form id="event-configuration-form" method="POST" action="{{ route('admin.configurations.event.update') }}" class="configuration-form">
            @csrf
            @method('PUT')

            <div class="configuration-grid">
                <label class="configuration-field">
                    <span>Event Name <b>*</b></span>
                    <input type="text" name="event_name" value="{{ old('event_name', $eventConfiguration?->event_name) }}" maxlength="255" required autofocus placeholder="e.g. Sri Lanka Tech Expo 2026">
                    @error('event_name')<small>{{ $message }}</small>@enderror
                </label>

                <label class="configuration-field">
                    <span>Event Location <b>*</b></span>
                    <input type="text" name="event_location" value="{{ old('event_location', $eventConfiguration?->event_location) }}" maxlength="255" required placeholder="e.g. BMICH, Colombo">
                    @error('event_location')<small>{{ $message }}</small>@enderror
                </label>

                <fieldset class="configuration-field configuration-period">
                    <legend>Event Period <b>*</b></legend>
                    <div class="configuration-date-range">
                        <label>
                            <span>Calendar</span>
                            <input type="date" name="starts_on" value="{{ old('starts_on', $eventConfiguration?->starts_on?->format('Y-m-d')) }}" required aria-label="Event start date">
                        </label>
                        <i aria-hidden="true">→</i>
                        <label>
                            <span>Calendar</span>
                            <input type="date" name="ends_on" value="{{ old('ends_on', $eventConfiguration?->ends_on?->format('Y-m-d')) }}" required aria-label="Event end date">
                        </label>
                    </div>
                    <em>Calendar to Calendar</em>
                    @error('starts_on')<small>{{ $message }}</small>@enderror
                    @error('ends_on')<small>{{ $message }}</small>@enderror
                </fieldset>

                <label class="configuration-field">
                    <span>Organized By <b>*</b></span>
                    <input type="text" name="organized_by" value="{{ old('organized_by', $eventConfiguration?->organized_by) }}" maxlength="255" required placeholder="e.g. Needle Innovations">
                    @error('organized_by')<small>{{ $message }}</small>@enderror
                </label>

            </div>

            <div class="configuration-actions">
                <p><strong>One active event</strong><br>This form updates the existing configuration when saved again.</p>
                <button type="submit" class="btn btn-primary">
                    {{ $eventConfiguration ? 'Update Configuration' : 'Save Configuration' }}
                    <span>→</span>
                </button>
            </div>
        </form>
    </section>

    @if($eventConfiguration)
        <section id="daily-registration-forms" class="admin-panel event-registration-days-panel">
            <div class="configuration-panel-heading">
                <div>
                    <span>DAILY REGISTRATION FORMS</span>
                    <h2>Registration by event date</h2>
                    <p>Each form creates an independently paid visitor pass that is valid only on its assigned date.</p>
                </div>
                <form method="POST" action="{{ route('admin.configurations.event.days.generate') }}" class="event-day-generate-form">
                    @csrf
                    <input type="number" name="entrance_fee" min="0.01" step="0.01" required placeholder="Fee (LKR)" aria-label="Entrance fee for generated daily forms">
                    <button class="btn btn-secondary" type="submit">Generate all event days</button>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.configurations.event.days.store') }}" class="event-day-create-form">
                @csrf
                <label><span>Form name</span><input name="label" value="{{ old('label') }}" maxlength="120" required placeholder="Registration for Day {{ $registrationDays->count() + 1 }}">@error('label')<small>{{ $message }}</small>@enderror</label>
                <label><span>Event date</span><input type="date" name="event_date" value="{{ old('event_date') }}" min="{{ $eventConfiguration->starts_on->format('Y-m-d') }}" max="{{ $eventConfiguration->ends_on->format('Y-m-d') }}" required>@error('event_date')<small>{{ $message }}</small>@enderror</label>
                <label><span>Entrance fee (LKR)</span><input type="number" name="entrance_fee" value="{{ old('entrance_fee') }}" min="0.01" step="0.01" required placeholder="e.g. 1500.00">@error('entrance_fee')<small>{{ $message }}</small>@enderror</label>
                <label class="event-day-open"><input type="checkbox" name="is_active" value="1" checked><span>Open for registration</span></label>
                <button class="btn btn-primary" type="submit">Add daily form</button>
            </form>

            @if($registrationDays->isNotEmpty())
                <div class="event-day-list">
                    @foreach($registrationDays as $day)
                        <article class="event-day-row">
                            <form id="event-day-update-{{ $day->id }}" method="POST" action="{{ route('admin.configurations.event.days.update', $day) }}">
                                @csrf
                                @method('PUT')
                            </form>
                            <input form="event-day-update-{{ $day->id }}" name="label" value="{{ $day->label }}" maxlength="120" required aria-label="Registration form name">
                            <input form="event-day-update-{{ $day->id }}" type="date" name="event_date" value="{{ $day->event_date->format('Y-m-d') }}" min="{{ $eventConfiguration->starts_on->format('Y-m-d') }}" max="{{ $eventConfiguration->ends_on->format('Y-m-d') }}" required aria-label="Event date">
                            <input form="event-day-update-{{ $day->id }}" type="number" name="entrance_fee" value="{{ $day->entrance_fee }}" min="0.01" step="0.01" required aria-label="Entrance fee">
                            <input form="event-day-update-{{ $day->id }}" type="hidden" name="is_active" value="{{ $day->is_active ? 1 : 0 }}">
                            <span class="event-day-status {{ $day->is_active ? 'open' : 'closed' }}">{{ $day->is_active ? 'Open' : 'Closed' }}</span>
                            <span class="event-day-count">{{ $day->visitors_count ?? $day->visitors()->count() }} registrations</span>
                            <div class="event-day-actions">
                                <button form="event-day-update-{{ $day->id }}" class="event-day-save" type="submit">Save</button>
                                <form method="POST" action="{{ route('admin.configurations.event.days.toggle', $day) }}">@csrf @method('PATCH')<button type="submit">{{ $day->is_active ? 'Close' : 'Open' }}</button></form>
                                <form method="POST" action="{{ route('admin.configurations.event.days.destroy', $day) }}" onsubmit="return confirm('Remove this daily registration form?');">@csrf @method('DELETE')<button class="danger" type="submit">Delete</button></form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="event-directory-empty"><span>+</span><h3>No daily forms configured</h3><p>Generate all event dates or add only the dates you want to open.</p></div>
            @endif
        </section>
    @else
        <section id="daily-registration-forms" class="admin-panel event-registration-days-panel event-registration-days-locked">
            <div class="configuration-panel-heading">
                <div>
                    <span>DAILY REGISTRATION FORMS</span>
                    <h2>Configure the event first</h2>
                    <p>Daily forms need the event start and end dates before they can be generated.</p>
                </div>
                <span class="event-day-step-badge">STEP 2</span>
            </div>
            <div class="event-day-prerequisite">
                <span>1</span>
                <div><h3>Save the active event details above</h3><p>Enter the event name, location, date period, and organizer. After saving, return here to generate Day 1, Day 2, and the remaining daily forms.</p></div>
                <a class="btn btn-primary" href="#event-configuration-form">Configure event</a>
            </div>
        </section>
    @endif

    <section class="admin-panel event-directory-panel">
        <div class="configuration-panel-heading">
            <div>
                <span>SAVED EVENT</span>
                <h2>Event configuration directory</h2>
                <p>Review, edit, or remove the event currently used by visitor management.</p>
            </div>
            @if($eventConfiguration)
                <span class="configuration-active-badge"><i></i> Active</span>
            @endif
        </div>

        @if($eventConfiguration)
            <div class="event-directory-table-wrap">
                <table class="event-directory-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Location</th>
                            <th>Period</th>
                            <th>Organized By</th>
                            <th class="event-actions-heading">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>{{ $eventConfiguration->event_name }}</strong></td>
                            <td>{{ $eventConfiguration->event_location }}</td>
                            <td>{{ $eventConfiguration->starts_on?->format('d M Y') }} &ndash; {{ $eventConfiguration->ends_on?->format('d M Y') }}</td>
                            <td>{{ $eventConfiguration->organized_by }}</td>
                            <td>
                                <div class="event-row-actions">
                                    <a href="#event-configuration-form">Edit</a>
                                    <form method="POST" action="{{ route('admin.configurations.event.destroy') }}" onsubmit="return confirm('Remove {{ addslashes($eventConfiguration->event_name) }}? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div class="event-directory-empty">
                <span>+</span>
                <h3>No event configured yet</h3>
                <p>Save the event details above to make them available throughout visitor management.</p>
            </div>
        @endif
    </section>
@endsection

@push('styles')
<style>
    body.landing-page .event-directory-panel { margin-top:22px; }
    body.landing-page .event-registration-days-panel { margin-top:22px; }
    body.landing-page #daily-registration-forms { scroll-margin-top:24px; }
    body.landing-page .event-day-step-badge { padding:7px 11px; color:#64720d; background:#eef6c9; border:1px solid #d8e89a; border-radius:999px; font-size:9px; font-weight:800; letter-spacing:.08em; }
    body.landing-page .event-day-prerequisite { display:grid; grid-template-columns:46px minmax(0,1fr) auto; align-items:center; gap:16px; padding:26px 28px; border-top:1px solid #edf0f2; }
    body.landing-page .event-day-prerequisite > span { display:grid; place-items:center; width:46px; height:46px; color:#334000; background:#c8e063; border-radius:13px; font-size:18px; font-weight:800; }
    body.landing-page .event-day-prerequisite h3 { margin:0 0 5px; color:#253043; font-size:14px; }
    body.landing-page .event-day-prerequisite p { max-width:700px; margin:0; color:#7c8997; font-size:11px; line-height:1.55; }
    body.landing-page .event-registration-days-panel .configuration-panel-heading > form { margin:0; }
    body.landing-page .event-day-generate-form { display:flex; align-items:center; gap:8px; }
    body.landing-page .event-day-generate-form input { width:120px; min-height:40px; padding:8px 10px; border:1px solid #dbe2eb; border-radius:8px; font:600 10px Inter,sans-serif; }
    body.landing-page .event-day-create-form { display:grid; grid-template-columns:minmax(220px,1fr) minmax(160px,.55fr) minmax(140px,.45fr) auto auto; align-items:end; gap:12px; padding:20px 22px; border-top:1px solid #edf0f2; }
    body.landing-page .event-day-create-form label:not(.event-day-open) { display:grid; gap:7px; color:#667085; font-size:9px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
    body.landing-page .event-day-create-form input:not([type=checkbox]), body.landing-page .event-day-row input { min-height:42px; padding:9px 11px; border:1px solid #dbe2eb; border-radius:8px; background:#fff; color:#172033; font:600 11px Inter,sans-serif; }
    body.landing-page .event-day-create-form small { color:#bd343d; text-transform:none; }
    body.landing-page .event-day-open { display:flex; align-items:center; gap:8px; min-height:42px; color:#52601f; font-size:10px; font-weight:800; }
    body.landing-page .event-day-list { border-top:1px solid #edf0f2; }
    body.landing-page .event-day-row { display:grid; grid-template-columns:minmax(200px,1fr) 150px 120px auto auto auto; align-items:center; gap:10px; padding:13px 22px; border-bottom:1px solid #edf0f2; }
    body.landing-page .event-day-status { padding:6px 9px; border-radius:999px; font-size:9px; font-weight:800; text-align:center; text-transform:uppercase; }
    body.landing-page .event-day-status.open { color:#526600; background:#f1f8d4; }
    body.landing-page .event-day-status.closed { color:#8b3a3a; background:#fff0f1; }
    body.landing-page .event-day-count { color:#75808e; font-size:10px; font-weight:700; white-space:nowrap; }
    body.landing-page .event-day-actions { display:flex; align-items:center; gap:6px; }
    body.landing-page .event-day-actions form { margin:0; }
    body.landing-page .event-day-actions button { min-height:31px; padding:0 9px; border:1px solid #d7dfc0; border-radius:6px; background:#f7faed; color:#52601f; font:800 9px Inter,sans-serif; cursor:pointer; }
    body.landing-page .event-day-actions .event-day-save { background:#c8e063; }
    body.landing-page .event-day-actions button.danger { color:#b4232d; background:#fff5f5; border-color:#f2c9cc; }
    body.landing-page .event-directory-table-wrap { overflow-x:auto; }
    body.landing-page .event-directory-table { width:100%; min-width:760px; border-collapse:collapse; }
    body.landing-page .event-directory-table th { padding:12px 22px; color:#7b8795; background:#f8faf8; text-align:left; font-size:9px; font-weight:800; letter-spacing:.7px; text-transform:uppercase; }
    body.landing-page .event-directory-table td { padding:15px 22px; color:#4b5b6d; border-top:1px solid #edf0f2; font-size:11px; font-weight:600; vertical-align:middle; }
    body.landing-page .event-directory-table td strong { color:#253043; font-size:12px; }
    body.landing-page .event-actions-heading { text-align:right; }
    body.landing-page .event-row-actions { display:flex; justify-content:flex-end; align-items:center; gap:9px; }
    body.landing-page .event-row-actions a, body.landing-page .event-row-actions button { display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 10px; border-radius:6px; font:800 10px Inter,sans-serif; text-decoration:none; cursor:pointer; }
    body.landing-page .event-row-actions a { color:#536b00; background:#f2f8db; border:1px solid #dcebad; }
    body.landing-page .event-row-actions form { margin:0; }
    body.landing-page .event-row-actions button { color:#c53e3e; background:#fff5f5; border:1px solid #f7d2d2; }
    body.landing-page .event-directory-empty { padding:36px; text-align:center; }
    body.landing-page .event-directory-empty > span { display:grid; place-items:center; width:34px; height:34px; margin:0 auto 10px; color:#75880d; background:#f1f7d5; border-radius:50%; font-weight:800; }
    body.landing-page .event-directory-empty h3 { margin:0; color:#253043; font-size:14px; }
    body.landing-page .event-directory-empty p { margin:6px 0 0; color:#7c8997; font-size:11px; }
    @media (max-width:900px) { body.landing-page .event-day-create-form, body.landing-page .event-day-row { grid-template-columns:1fr 1fr; } body.landing-page .event-day-actions { grid-column:1/-1; } }
    @media (max-width:700px) { body.landing-page .event-directory-panel .configuration-panel-heading { display:block; } body.landing-page .event-directory-panel .configuration-active-badge { display:inline-flex; margin-top:14px; } body.landing-page .event-day-create-form, body.landing-page .event-day-row { grid-template-columns:1fr; } body.landing-page .event-day-actions { grid-column:auto; flex-wrap:wrap; } body.landing-page .event-day-prerequisite { grid-template-columns:46px 1fr; } body.landing-page .event-day-prerequisite .btn { grid-column:1/-1; justify-content:center; } }
</style>
@endpush
