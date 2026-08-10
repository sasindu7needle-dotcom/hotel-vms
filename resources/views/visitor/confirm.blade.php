<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Details — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page visitor-confirmation-page">
    <main class="registration-shell confirmation-shell">
        <div class="registration-background" aria-hidden="true">
            <div class="registration-background-glow"></div>
            <div class="registration-background-art">@include('visitor.partials.checkin-illustration')</div>
            <span class="registration-accent registration-accent-lime"></span>
            <span class="registration-accent registration-accent-coral"></span>
        </div>

        <section class="registration-card confirmation-card" aria-labelledby="confirmation-title">
            @if(data_get($details, 'registration_date'))
                <div style="margin-bottom:16px;padding:11px 14px;color:#52601f;background:#f3f8de;border:1px solid #dce8aa;border-radius:10px;font-size:11px;font-weight:800">
                    {{ data_get($details, 'registration_day_label') }} · {{ \Illuminate\Support\Carbon::parse(data_get($details, 'registration_date'))->format('l, d F Y') }} · Separate payment
                </div>
            @endif
            <div class="registration-heading confirmation-heading">
                <span class="tagline no-margin">FINAL REVIEW</span>
                <h1 id="confirmation-title" class="headline">Confirm your details<span class="dot">.</span></h1>
                <p>Review your verified information and choose how you would like to pay.</p>
            </div>

            <div class="confirmation-profile">
                <div class="visitor-photo-frame">
                    @if(data_get($details, 'selfie_path'))
                        <img src="{{ route('visitor.session_photo', ['type' => 'selfie']) }}" alt="Live camera visitor photo" onerror="this.onerror=null; this.src='{{ route('visitor.session_photo', ['type' => 'photo']) }}';">
                    @elseif(data_get($details, 'photo_url'))
                        <img src="{{ $details['photo_url'] }}" alt="Verified visitor photo" onerror="this.onerror=null; this.src='{{ route('visitor.session_photo', ['type' => 'photo']) }}';">
                    @elseif(data_get($details, 'photo_path'))
                        <img src="{{ route('visitor.session_photo', ['type' => 'photo']) }}" alt="Verified visitor photo">
                    @else
                        <div class="visitor-photo-placeholder" aria-label="Visitor photo unavailable">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
                        </div>
                    @endif
                    <span class="verified-photo-badge">VERIFIED IDENTITY</span>
                </div>

                <div class="confirmation-details-grid">
                    @foreach([
                        'full_name' => 'Full Name',
                        'mobile_number' => 'Mobile Number',
                        'whatsapp_number' => 'WhatsApp Number',
                        'address' => 'Address',
                        'occupation' => 'Occupation',
                        'company' => 'Company',
                        'entrance_fee' => 'Entrance Fee',
                        'category' => 'Category'
                    ] as $key => $label)
                        <div class="confirmation-detail {{ $key === 'address' ? 'confirmation-detail-wide' : '' }}">
                            <span>{{ $label }}</span>
                            <strong>
                                @if(in_array($key, ['mobile_number', 'whatsapp_number']))
                                    +94 {{ $details[$key] }}
                                @elseif($key === 'entrance_fee')
                                    {{ $details[$key] !== null ? 'LKR '.number_format((float) $details[$key], 2) : 'Not assigned' }}
                                @else
                                    {{ filled($details[$key] ?? null) ? $details[$key] : '—' }}
                                @endif
                            </strong>
                        </div>
                    @endforeach
                </div>
            </div>

            <form action="{{ route('visitor.payment-method') }}" method="POST" class="payment-choice-form">
                @csrf
                <fieldset>
                    <legend>Choose a payment method</legend>
                    <p class="payment-help">Select how you would like to pay.</p>

                    <div class="payment-option-grid">
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="visa_master" @checked(old('payment_method') === 'visa_master') required>
                            <span class="payment-option-indicator"></span>
                            <span class="payment-option-copy"><strong>Visa / Master</strong><small>Credit or debit card</small></span>
                            <span class="card-marks"><b>VISA</b><b>MC</b></span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="amex" @checked(old('payment_method') === 'amex') required>
                            <span class="payment-option-indicator"></span>
                            <span class="payment-option-copy"><strong>American Express</strong><small>Pay securely by Amex</small></span>
                            <span class="amex-mark">AMEX</span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="cash" @checked(old('payment_method') === 'cash') required>
                            <span class="payment-option-indicator"></span>
                            <span class="payment-option-copy"><strong>Cash</strong><small>Pay at the entrance counter</small></span>
                            <span class="cash-mark">LKR</span>
                        </label>
                    </div>
                    @error('payment_method')<span class="form-error-msg">{{ $message }}</span>@enderror
                </fieldset>

                <div class="confirmation-actions">
                    <a href="{{ route('visitor.create', ['type' => $details['document_type'], 'verified' => 'true']) }}" class="btn-back-link">Back to edit</a>
                    <button type="submit" class="btn btn-primary btn-large confirmation-pay-button">Continue to payment</button>
                </div>
            </form>
        </section>
        <footer class="registration-trust">Secure payment routing. Your verified identity details are encrypted and used only for this visit.</footer>
    </main>
</body>
</html>
