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
    <nav class="configuration-tabs" aria-label="Master configuration sections">
        <a class="active" href="{{ route('admin.configurations.event.edit') }}" aria-current="page">Event Configurations</a>
        <a href="{{ route('admin.configurations.capacity.edit') }}">Occupancy Limit</a>
        <a href="{{ route('admin.configurations.categories.index') }}">Visitor Categories</a>
        <a href="{{ route('admin.configurations.users.index') }}">Users &amp; Access</a>
    </nav>

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

        <form method="POST" action="{{ route('admin.configurations.event.update') }}" class="configuration-form">
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
@endsection
