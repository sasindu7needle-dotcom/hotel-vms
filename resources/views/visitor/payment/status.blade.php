<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>.payment-reference{display:grid;gap:4px;margin-top:16px;padding:13px;background:#f7f9f2;border-radius:9px}.payment-reference span{font-size:10px;color:#64748b}.payment-reference strong{font-size:13px}.payment-retry{margin-top:18px}.payment-status-failed{color:#991b1b}.payment-status-pending{color:#735500}</style>
</head>
<body class="landing-page visitor-registration-page">
    @include('layouts.site-header')
    <main class="registration-shell payment-status-shell">
        <div class="registration-background" aria-hidden="true"><div class="registration-background-glow"></div><div class="registration-background-art"><img src="{{ asset('img/hero.png') }}" alt="" class="hero-image"></div></div>
        <section class="registration-card payment-status-card" aria-live="polite">
            @if($payment->status === 'pending')
                <span class="tagline no-margin">VERIFYING PAYMENT</span>
                <h1 class="headline payment-status-pending">Verification in progress<span class="dot">.</span></h1>
                <p>DirectPay is processing the transaction. This page will update automatically after secure server confirmation.</p>
            @else
                <span class="tagline no-margin">PAYMENT NOT COMPLETED</span>
                <h1 class="headline payment-status-failed">Payment unsuccessful<span class="dot">.</span></h1>
                <p>Your registration has not been marked as paid. You can safely try the card payment again.</p>
                <a class="btn btn-primary btn-large payment-retry" href="{{ route('visitor.payment.card') }}">Try payment again</a>
            @endif
            <div class="payment-amount"><span>Amount</span><strong>{{ $payment->currency }} {{ number_format((float) $payment->expected_amount, 2) }}</strong></div>
            <div class="payment-reference"><span>PAYMENT REFERENCE</span><strong>{{ $payment->reference }}</strong></div>
        </section>
        <footer class="registration-trust">Only DirectPay's verified server response can mark this registration paid.</footer>
    </main>
    @if($payment->status === 'pending')
        <script>window.setTimeout(() => window.location.reload(), 4000);</script>
    @endif
</body>
</html>
