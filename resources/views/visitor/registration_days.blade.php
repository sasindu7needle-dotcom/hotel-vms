<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Event Day &mdash; Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page">
<main class="registration-shell">
    <div class="registration-background" aria-hidden="true">
        <div class="registration-background-glow"></div>
        <div class="registration-background-art">
            <img src="{{ asset('img/hero.png') }}" alt="" class="hero-image">
        </div>
        <span class="registration-accent registration-accent-lime"></span>
        <span class="registration-accent registration-accent-coral"></span>
    </div>

    <section class="registration-card event-day-card" aria-labelledby="event-day-title">
        <div class="registration-heading event-day-heading">
            <span class="tagline no-margin">DAILY REGISTRATION</span>
            <h1 id="event-day-title" class="headline">Choose your event day<span class="dot">.</span></h1>
            <p>Each day requires a separate registration and payment. Your QR pass will work only on the selected date.</p>
            @if($eventConfiguration)
                <div class="event-day-location-badge">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                    <span>{{ $eventConfiguration->event_name }} &middot; {{ $eventConfiguration->event_location }}</span>
                </div>
            @endif
        </div>

        @if($errors->any())
            <div class="event-day-alert-error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if($registrationDays->isNotEmpty())
            <div class="event-day-grid">
                @foreach($registrationDays as $day)
                    <article class="event-day-item">
                        <div class="event-day-item-body">
                            <div class="event-day-date-box">
                                <span class="event-day-date-num">{{ $day->event_date->format('d') }}</span>
                                <span class="event-day-date-month">{{ $day->event_date->format('M Y') }}</span>
                            </div>
                            <div class="event-day-info">
                                <h2>{{ $day->label }}</h2>
                                <p class="event-day-full-date">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    {{ $day->event_date->format('l, d F Y') }}
                                </p>
                                <div class="event-day-fee-row">
                                    <span class="event-day-fee-amount">LKR {{ number_format((float) $day->entrance_fee, 2) }}</span>
                                    <span class="event-day-fee-unit">per registration</span>
                                </div>
                            </div>
                        </div>
                        <div class="event-day-item-footer">
                            <form method="POST" action="{{ route('visitor.registration-days.select') }}">
                                @csrf
                                <input type="hidden" name="registration_day_id" value="{{ $day->id }}">
                                <button type="submit" class="btn btn-primary btn-select-day">
                                    <span>Register for this day</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="arrow-icon" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="event-day-empty-state">
                <div class="event-day-empty-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><line x1="10" y1="14" x2="14" y2="18"></line><line x1="14" y1="14" x2="10" y2="18"></line></svg>
                </div>
                <h2>No registration forms are open</h2>
                <p>Please contact the event organizer or check again later.</p>
            </div>
        @endif
    </section>
</main>
</body>
</html>
