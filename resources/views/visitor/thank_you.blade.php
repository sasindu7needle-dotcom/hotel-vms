<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Registering — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        body.thank-you-page .thank-you-download { display:inline-flex; align-items:center; justify-content:center; gap:9px; width:min(100%,350px); min-height:50px; margin-top:22px; padding:12px 20px; color:#fff; background:#17233f; border:1px solid #17233f; border-radius:10px; font-size:13px; font-weight:800; letter-spacing:.025em; text-decoration:none; transition:transform .18s ease, box-shadow .18s ease, background .18s ease; }
        body.thank-you-page .thank-you-download:hover { background:#223253; box-shadow:0 10px 24px rgba(23,35,63,.18); transform:translateY(-1px); }
        body.thank-you-page .thank-you-download:focus-visible { outline:3px solid rgba(200,224,99,.65); outline-offset:3px; }
        body.thank-you-page .thank-you-download svg { width:19px; height:19px; fill:none; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:2; }
    </style>
</head>
<body class="landing-page visitor-registration-page thank-you-page">
    @include('layouts.site-header')
    <main class="registration-shell thank-you-shell">
        <div class="registration-background" aria-hidden="true">
            <div class="registration-background-glow"></div>
            <div class="registration-background-art"><img src="{{ asset('img/hero.png') }}" alt="" class="hero-image"></div>
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
                    @if(data_get($details, 'registration_date'))
                        <small>{{ data_get($details, 'registration_day_label') }} · {{ \Illuminate\Support\Carbon::parse(data_get($details, 'registration_date'))->format('d M Y') }}</small>
                    @endif
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

            <a class="thank-you-download" href="{{ route('visitor.card.download') }}" download>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                <span>Download Entrance Card</span>
            </a>

            @if(!data_get($details, 'exhibitor_profile_token'))
            <p class="printing-instruction">Please proceed to the <strong>Printing Booth</strong> to collect your Entrance ID.</p>
            @if(data_get($details, 'registration_date'))
                <p class="printing-instruction" style="margin-top:8px"><strong>This QR is valid only on {{ \Illuminate\Support\Carbon::parse(data_get($details, 'registration_date'))->format('d F Y') }}.</strong></p>
            @endif
            @endif
            @if(data_get($details, 'exhibitor_profile_token'))
                <p class="printing-instruction">Your member registration is complete. <strong>Event administration will print the entrance card.</strong></p>
                <div style="display:flex;justify-content:center;margin-top:18px;"><a class="btn" style="background:#fff;border:1px solid #d8e0e7;color:#172033" href="{{ route('exhibitor.dashboard', data_get($details, 'exhibitor_profile_token')) }}">Back to members</a></div>
            @endif
        </section>
    </main>
</body>
</html>
