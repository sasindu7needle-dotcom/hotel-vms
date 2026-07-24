<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Card Payment — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page">
    <main class="registration-shell payment-status-shell">
        <div class="registration-background" aria-hidden="true"><div class="registration-background-glow"></div><div class="registration-background-art">@include('visitor.partials.checkin-illustration')</div></div>
        <section class="registration-card payment-status-card">
            <div class="payment-status-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20M6 15h4"></path></svg>
            </div>
            <span class="tagline no-margin">SECURE CARD GATEWAY</span>
            <h1 class="headline">Ready for payment<span class="dot">.</span></h1>
            <p>You selected {{ $details['payment_method'] === 'amex' ? 'American Express' : 'Visa / Master' }}. Continue through the configured payment provider to complete your entrance-fee payment.</p>
            <div class="payment-amount"><span>Amount due</span><strong>{{ $details['entrance_fee'] !== null ? 'LKR '.number_format((float) $details['entrance_fee'], 2) : 'Not assigned' }}</strong></div>
            <form action="{{ route('visitor.payment.confirm') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary btn-large registration-next">Confirm Payment</button>
            </form>
            <small class="payment-provider-note">This confirmation continues from your configured secure payment provider.</small>
        </section>
        <footer class="registration-trust">Card information will be handled by the secure payment provider.</footer>
    </main>
</body>
</html>
