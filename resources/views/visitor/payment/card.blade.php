<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Card Payment — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        .directpay-email { display:grid; gap:8px; margin:20px 0; text-align:left; }
        .directpay-email input { box-sizing:border-box; width:100%; min-height:48px; padding:11px 13px; border:1px solid #d8e0e7; border-radius:9px; font:600 13px Inter,sans-serif; }
        .directpay-alert { margin:14px 0; padding:12px 14px; color:#991b1b; background:#fff1f1; border:1px solid #fecaca; border-radius:9px; font-size:12px; }
        .directpay-loading { margin-top:15px; color:#64748b; font-size:12px; }
        #card_container { margin-top:20px; min-height:180px; }
    </style>
</head>
<body class="landing-page visitor-registration-page">
    @include('layouts.site-header')
    <main class="registration-shell payment-status-shell">
        <div class="registration-background" aria-hidden="true"><div class="registration-background-glow"></div><div class="registration-background-art"><img src="{{ asset('img/hero.png') }}" alt="" class="hero-image"></div></div>
        <section class="registration-card payment-status-card">
            <div class="payment-status-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20M6 15h4"></path></svg>
            </div>
            <span class="tagline no-margin">DIRECTPAY SANDBOX</span>
            <h1 class="headline">Secure card payment<span class="dot">.</span></h1>
            @if(data_get($details, 'registration_date'))<p><strong>{{ data_get($details, 'registration_day_label') }} · {{ \Illuminate\Support\Carbon::parse(data_get($details, 'registration_date'))->format('d F Y') }}</strong></p>@endif
            <p>DirectPay securely handles your card details and 3DS authentication.</p>
            <div class="payment-amount"><span>Amount due</span><strong>LKR {{ number_format((float) $visitor->entrance_fee, 2) }}</strong></div>

            @if($errors->any())
                <div class="directpay-alert" role="alert">{{ $errors->first() }}</div>
            @endif

            @if($payment)
                <div id="card_container" aria-label="DirectPay secure card form"></div>
                <p id="directpay-message" class="directpay-loading" role="status">Loading DirectPay secure payment form…</p>
                <noscript><div class="directpay-alert">JavaScript is required to use the secure card payment form.</div></noscript>
            @else
                <form action="{{ route('visitor.payment.directpay.start', $visitor) }}" method="POST" id="directpay-start-form">
                    @csrf
                    <label class="directpay-email" for="payment-email">
                        <span class="form-label-premium">Email address</span>
                        <input id="payment-email" name="email" type="email" maxlength="100" value="{{ old('email', $visitor->email) }}" autocomplete="email" required>
                    </label>
                    <button type="submit" class="btn btn-primary btn-large registration-next" id="directpay-start-button" @disabled(! $directPayConfigured)>Pay securely</button>
                </form>
                @unless($directPayConfigured)
                    <div class="directpay-alert">DirectPay sandbox credentials have not been configured.</div>
                @endunless
            @endif
            <small class="payment-provider-note">The amount is loaded from your saved registration. Card details are never sent to or stored by this website.</small>
        </section>
        <footer class="registration-trust">Your payment is confirmed only after secure server verification.</footer>
    </main>

    @if($payment)
        <script src="https://cdn.directpay.lk/dev/v1/directpayCardPayment.js?v=1"></script>
        <script>
            (() => {
                const message = document.getElementById('directpay-message');
                const statusUrl = @json(route('visitor.payment.directpay.status', $payment->reference));
                const config = @json($directPayConfig);

                function awaitingVerification() {
                    message.textContent = 'Payment submitted. Waiting for secure server verification…';
                    window.setTimeout(() => window.location.assign(statusUrl), 1200);
                }

                function paymentError() {
                    message.textContent = 'The payment was not completed. You may retry safely.';
                }

                if (typeof window.DirectPayCardPayment === 'undefined') {
                    message.textContent = 'The DirectPay payment form could not load. Check your connection and retry.';
                    return;
                }

                try {
                    config.responseCallback = awaitingVerification;
                    config.errorCallback = paymentError;
                    window.DirectPayCardPayment.init(config);
                    if (document.getElementById('dpMainContainer')) {
                        message.textContent = 'Enter your card details in the secure DirectPay form.';
                    } else {
                        message.textContent = 'DirectPay could not initialize the card form. Verify the sandbox merchant settings and try again.';
                    }
                } catch (error) {
                    paymentError();
                }
            })();
        </script>
    @else
        <script>
            document.getElementById('directpay-start-form')?.addEventListener('submit', function () {
                const button = document.getElementById('directpay-start-button');
                button.disabled = true;
                button.textContent = 'Preparing secure payment…';
            });
        </script>
    @endif
</body>
</html>
