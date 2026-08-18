<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Payment Confirmation — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        body.cash-payment-page .cash-pending-notice { display:grid; gap:5px; margin-top:18px; padding:15px 17px; color:#52601f; background:#f4f8df; border:1px solid #dce6b6; border-radius:12px; text-align:left; }
        body.cash-payment-page .cash-pending-notice strong { font-size:13px; }
        body.cash-payment-page .cash-pending-notice span { color:#68734a; font-size:12px; line-height:1.5; }
        body.cash-payment-page .card-download-action { display:flex; align-items:center; justify-content:center; gap:9px; width:100%; min-height:50px; margin-top:14px; padding:12px 18px; color:#fff; background:#17233f; border:1px solid #17233f; border-radius:10px; font-size:13px; font-weight:800; letter-spacing:.025em; text-decoration:none; transition:transform .18s ease, box-shadow .18s ease, background .18s ease; }
        body.cash-payment-page .card-download-action:hover { background:#223253; box-shadow:0 10px 24px rgba(23,35,63,.18); transform:translateY(-1px); }
        body.cash-payment-page .card-download-action:focus-visible { outline:3px solid rgba(200,224,99,.65); outline-offset:3px; }
        body.cash-payment-page .card-download-action svg { width:19px; height:19px; fill:none; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:2; }
        body.cash-payment-page .card-download-help { display:block; margin-top:8px; color:#718096; font-size:11px; line-height:1.45; }
    </style>
</head>
<body class="landing-page visitor-registration-page cash-payment-page">
    <main class="registration-shell payment-status-shell cash-payment-shell">
        <div class="registration-background" aria-hidden="true"><div class="registration-background-glow"></div><div class="registration-background-art"><img src="{{ asset('img/hero.png') }}" alt="" class="hero-image"></div></div>
        <section class="registration-card payment-status-card cash-payment-card" aria-labelledby="cash-payment-title">
            <div class="payment-status-icon payment-status-icon-cash">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="3"></circle><path d="M6 9H5v1M18 15h1v-1"></path></svg>
            </div>
            <span class="tagline no-margin">CASH SELECTED</span>
            <h1 id="cash-payment-title" class="headline">Pay at the counter<span class="dot">.</span></h1>
            @if(data_get($details, 'registration_date'))<p><strong>{{ data_get($details, 'registration_day_label') }} · {{ \Illuminate\Support\Carbon::parse(data_get($details, 'registration_date'))->format('d F Y') }}</strong></p>@endif
            <p>Your details are confirmed. Please present this screen at the entrance counter and make the cash payment to complete check-in.</p>
            <div class="payment-amount"><span>Cash amount due</span><strong>{{ data_get($details, 'entrance_fee') !== null ? 'LKR '.number_format((float) data_get($details, 'entrance_fee'), 2) : 'Confirm at counter' }}</strong></div>
            <div class="cash-reference"><span>Visitor</span><strong>{{ data_get($details, 'full_name') ?: 'Verified visitor' }}</strong></div>
            <div class="cash-pending-notice" role="status">
                <strong>Waiting for payment confirmation</strong>
                <span>Reception will update your payment. This page will continue automatically once payment is marked paid.</span>
            </div>
            <a class="card-download-action" href="{{ route('visitor.card.download') }}" download>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                <span>Download Entrance Card</span>
            </a>
            <small class="card-download-help">Your downloaded card will show payment as pending until reception confirms it.</small>
        </section>
        <footer class="registration-trust">Please collect an official receipt after making your payment.</footer>
    </main>
    <script>
        window.setTimeout(() => window.location.reload(), 10000);
    </script>
</body>
</html>
