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
    @media (max-width:700px) { body.landing-page .event-directory-panel .configuration-panel-heading { display:block; } body.landing-page .event-directory-panel .configuration-active-badge { display:inline-flex; margin-top:14px; } }
</style>
@endpush
