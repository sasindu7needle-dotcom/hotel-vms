@extends('layouts.admin')

@section('title', 'Occupancy Limit')

@push('styles')
<style>
    body.landing-page .capacity-configuration-panel{max-width:1080px}
    body.landing-page .capacity-empty-state{display:grid;grid-template-columns:64px minmax(0,1fr) auto;align-items:center;gap:20px;padding:30px 32px;background:#fff}
    body.landing-page .capacity-empty-icon{display:grid;width:60px;height:60px;place-items:center;color:#75880d;background:#f1f7d5;border:1px solid #d8e59d;border-radius:16px}
    body.landing-page .capacity-empty-icon svg{width:29px;height:29px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
    body.landing-page .capacity-empty-copy h3{margin:0 0 6px;color:#172000;font-size:17px}
    body.landing-page .capacity-empty-copy p{max-width:560px;margin:0;color:#778390;font-size:11px;line-height:1.55}
    body.landing-page .capacity-empty-state>.btn{display:inline-flex;min-width:180px;min-height:46px;align-items:center;justify-content:center;gap:10px;text-decoration:none}
    body.landing-page .capacity-form{padding:0}
    body.landing-page .capacity-setting-grid{display:grid;grid-template-columns:minmax(280px,1fr) minmax(250px,.7fr);gap:24px;padding:28px}
    body.landing-page .capacity-field-card{padding:20px;background:#fafbf8;border:1px solid #e1e7da;border-radius:12px}
    body.landing-page .capacity-field-card input{font-size:16px}
    body.landing-page .capacity-field-card>em{color:#7c8777;font-size:10px;font-style:normal;line-height:1.45}
    body.landing-page .capacity-snapshot{display:grid;grid-template-columns:1fr 1fr;gap:10px;align-content:start}
    body.landing-page .capacity-snapshot article{padding:17px;background:#fff;border:1px solid #e1e6e9;border-left:4px solid #c8e063;border-radius:10px}
    body.landing-page .capacity-snapshot span,body.landing-page .capacity-snapshot strong{display:block}
    body.landing-page .capacity-snapshot span{color:#7b8794;font-size:8px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
    body.landing-page .capacity-snapshot strong{margin-top:8px;color:#111;font-size:24px}
    body.landing-page .capacity-form .configuration-actions{margin:0;padding:20px 28px;background:#fafbfb}
    @media(max-width:800px){body.landing-page .capacity-setting-grid{grid-template-columns:1fr}body.landing-page .capacity-empty-state{grid-template-columns:54px minmax(0,1fr)}body.landing-page .capacity-empty-state>.btn{grid-column:1/-1;width:100%}}
    @media(max-width:520px){body.landing-page .capacity-setting-grid{padding:20px 16px}body.landing-page .capacity-snapshot{grid-template-columns:1fr}body.landing-page .capacity-empty-state{grid-template-columns:1fr;padding:24px 18px}body.landing-page .capacity-empty-icon{width:50px;height:50px}}
</style>
@endpush

@section('header')
    <div>
        <span class="tagline no-margin">MASTER CONFIGURATIONS</span>
        <h1>Occupancy Limit<span>.</span></h1>
        <p>Control the maximum number of visitors allowed inside the event</p>
    </div>
@endsection

@section('content')

    @if(session('status'))
        <div class="admin-page-alert configuration-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
            {{ session('status') }}
        </div>
    @endif

    <section class="admin-panel configuration-panel capacity-configuration-panel">
        <div class="configuration-panel-heading">
            <div>
                <span>EVENT CAPACITY</span>
                <h2>Maximum visitors inside</h2>
                <p>This limit is enforced by gate QR scans, admin check-ins, and dashboard occupancy adjustments.</p>
            </div>
            @if($eventConfiguration)
                <span class="configuration-active-badge"><i></i> {{ number_format($insideCount) }} currently inside</span>
            @endif
        </div>

        @if($eventConfiguration)
            <form method="POST" action="{{ route('admin.configurations.capacity.update') }}" class="configuration-form capacity-form">
                @csrf
                @method('PUT')

                <div class="capacity-setting-grid">
                    <label class="configuration-field capacity-field-card">
                        <span>Maximum Visitors Inside <b>*</b></span>
                        <input type="number" name="capacity_limit" value="{{ old('capacity_limit', $eventConfiguration->capacity_limit) }}" min="{{ max(1, $insideCount) }}" max="1000000" step="1" required autofocus>
                        <em>Current occupancy: {{ number_format($insideCount) }}. The limit cannot be set below this number.</em>
                        @error('capacity_limit')<small>{{ $message }}</small>@enderror
                    </label>
                    <div class="capacity-snapshot" aria-label="Occupancy summary">
                        <article><span>Currently Inside</span><strong>{{ number_format($insideCount) }}</strong></article>
                        <article><span>Available Spaces</span><strong>{{ number_format(max(0, $eventConfiguration->capacity_limit - $insideCount)) }}</strong></article>
                    </div>
                </div>

                <div class="configuration-actions">
                    <p><strong>{{ $eventConfiguration->event_name }}</strong><br>Available spaces update automatically when the limit changes.</p>
                    <button type="submit" class="btn btn-primary">Update Occupancy Limit <span>→</span></button>
                </div>
            </form>
        @else
            <div class="capacity-empty-state">
                <div class="capacity-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 7v10M7 12h10"></path><circle cx="12" cy="12" r="9"></circle></svg>
                </div>
                <div class="capacity-empty-copy">
                    <h3>No active event configured</h3>
                    <p>Create the event first. After that, return here to set the maximum occupancy enforced by every gate and admin check-in.</p>
                </div>
                <a href="{{ route('admin.configurations.event.edit') }}" class="btn btn-primary">Create Event <span>→</span></a>
            </div>
        @endif
    </section>
@endsection
