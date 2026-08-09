<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Registering — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <meta http-equiv="refresh" content="{{ data_get($details, 'exhibitor_profile_token') ? '30;url='.route('exhibitor.dashboard', data_get($details, 'exhibitor_profile_token')) : '5;url='.url('/') }}">
</head>
<body class="landing-page visitor-registration-page thank-you-page">
    <main class="registration-shell thank-you-shell">
        <div class="registration-background" aria-hidden="true">
            <div class="registration-background-glow"></div>
            <div class="registration-background-art">@include('visitor.partials.checkin-illustration')</div>
            <span class="registration-accent registration-accent-lime"></span>
            <span class="registration-accent registration-accent-coral"></span>
        </div>

        <section class="thank-you-content" aria-labelledby="thank-you-title">
            <div class="thank-you-heading">
                <span class="success-check" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></svg>
                </span>
                <span class="tagline no-margin">REGISTRATION COMPLETE</span>
                <h1 id="thank-you-title" class="headline">Thank you for registering<span class="dot">.</span></h1>
            </div>

            <article class="entrance-badge" aria-label="Visitor entrance badge">
                <div class="badge-topbar"><span>ENTRANCE ID</span><span class="badge-status">VERIFIED</span></div>
                <header class="badge-event">
                    <span>EVENT NAME</span>
                    <h2>{{ $eventName }}</h2>
                </header>

                <div class="badge-photo">
                    @if(data_get($details, 'selfie_path') || data_get($details, 'photo_path'))
                        <img src="{{ route('visitor.session_photo', ['type' => 'selfie']) }}" alt="Photo of {{ data_get($details, 'full_name', 'visitor') }}" onerror="this.onerror=null; this.src='{{ route('visitor.session_photo', ['type' => 'photo']) }}';">
                    @elseif(data_get($details, 'photo_url'))
                        <img src="{{ $details['photo_url'] }}" alt="Photo of {{ data_get($details, 'full_name', 'visitor') }}">
                    @else
                        <div class="badge-photo-placeholder" aria-label="Visitor photo unavailable">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
                        </div>
                    @endif
                </div>

                <div class="badge-identity">
                    <span>VISITOR NAME</span>
                    <h3>{{ data_get($details, 'full_name', 'Verified Visitor') }}</h3>
                    <div class="badge-category"><span>CATEGORY</span><strong>{{ data_get($details, 'category', 'Visitor') }}</strong></div>
                </div>

                <div class="badge-qr">
                    <div class="badge-qr-code" role="img" aria-label="QR code for visitor ID {{ $qrPayload }}">{!! $qrCode !!}</div>
                    <div><span>PAYMENT REFERENCE</span><strong>{{ $paymentReference }}</strong></div>
                </div>
            </article>

            @if(!data_get($details, 'exhibitor_profile_token'))
                <p class="printing-instruction">Please proceed to the <strong>Printing Booth</strong> to collect your Entrance ID.</p>
            @endif
            @if(data_get($details, 'exhibitor_profile_token'))
                <p class="printing-instruction">Your member registration is complete. <strong>Event administration will print the entrance card.</strong></p>
                <div style="display:flex;justify-content:center;margin-top:18px;"><a class="btn" style="background:#fff;border:1px solid #d8e0e7;color:#172033" href="{{ route('exhibitor.dashboard', data_get($details, 'exhibitor_profile_token')) }}">Back to members</a></div>
                <p class="redirect-notice" style="margin-top: 14px; font-size: 11px; color: #64748b; font-weight: 600;">Returning to your member list in <span id="redirectCountdown">30</span> seconds...</p>
            @else
                <p class="redirect-notice" style="margin-top: 14px; font-size: 11px; color: #64748b; font-weight: 600;">Redirecting to home page in <span id="redirectCountdown">5</span> seconds...</p>
            @endif
        </section>
    </main>

    <script>
        (function() {
            let secondsLeft = {{ data_get($details, 'exhibitor_profile_token') ? 30 : 5 }};
            const countdownEl = document.getElementById('redirectCountdown');
            const timer = setInterval(() => {
                secondsLeft--;
                if (countdownEl) countdownEl.textContent = secondsLeft;
                if (secondsLeft <= 0) {
                    clearInterval(timer);
                    window.location.href = "{{ data_get($details, 'exhibitor_profile_token') ? route('exhibitor.dashboard', data_get($details, 'exhibitor_profile_token')) : url('/') }}";
                }
            }, 1000);
        })();
    </script>
</body>
</html>
