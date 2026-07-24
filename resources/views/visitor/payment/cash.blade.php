<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Payment Confirmation — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page cash-payment-page">
    <main class="registration-shell payment-status-shell cash-payment-shell">
        <div class="registration-background" aria-hidden="true"><div class="registration-background-glow"></div><div class="registration-background-art">@include('visitor.partials.checkin-illustration')</div></div>
        <section class="registration-card payment-status-card cash-payment-card" aria-labelledby="cash-payment-title">
            <div class="payment-status-icon payment-status-icon-cash">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="3"></circle><path d="M6 9H5v1M18 15h1v-1"></path></svg>
            </div>
            <span class="tagline no-margin">CASH SELECTED</span>
            <h1 id="cash-payment-title" class="headline">Pay at the counter<span class="dot">.</span></h1>
            <p>Your details are confirmed. Please present this screen at the entrance counter and make the cash payment to complete check-in.</p>
            <div class="payment-amount"><span>Cash amount due</span><strong>{{ $details['entrance_fee'] !== null ? 'LKR '.number_format((float) $details['entrance_fee'], 2) : 'Confirm at counter' }}</strong></div>
            <div class="cash-reference"><span>Visitor</span><strong>{{ $details['full_name'] ?: 'Verified visitor' }}</strong></div>
        </section>
        <footer class="registration-trust">Please collect an official receipt after making your payment.</footer>
    </main>
</body>
</html>
