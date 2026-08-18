<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Identity — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        body.landing-page .btn-continue-initial.disabled-link {
            background: #e2e8f0 !important;
            color: #64748b !important;
            opacity: 0.65 !important;
            pointer-events: none !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            transform: none !important;
        }
        body.landing-page .btn-continue-initial:not(.disabled-link) {
            background: #14213D !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            cursor: pointer !important;
            box-shadow: 0 8px 24px rgba(20, 33, 61, 0.24) !important;
            transition: all 0.25s cubic-bezier(0.2, 0.8, 0.2, 1) !important;
        }
        body.landing-page .btn-continue-initial:not(.disabled-link):hover {
            background: #0B132B !important;
            color: #ffffff !important;
            box-shadow: 0 10px 28px rgba(11, 19, 43, 0.32) !important;
            transform: translateY(-2px) !important;
        }
    </style>
</head>
<body class="landing-page verification-consent-page">
    @include('layouts.site-header')

    <section class="hero">
        <div class="hero-content">
            <div class="tagline">Check-in Flow</div>
            <h1 class="headline">Verify your identity<span class="dot">.</span></h1>
            @include('visitor.partials.selected-registration-day')

            @error('verification')
                <div class="alert-verified-badge verification-error-message" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e85d5d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4M12 16h.01"></path></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror
            
            <div class="verification-consent-card">
                <div class="verification-consent-heading">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
                    <div><h2>Secure document verification</h2></div>
                </div>
                
                <div class="privacy-summary">
                    <h3>What will be collected</h3>
                    <ul>
                        <li>Identity-document image (NIC, Passport, Driving License, or Identity Card).</li>
                        <li>Extracted text details (Full Name, Document Number, and Address).</li>
                        <li>Encrypted session signals and check-in verification result.</li>
                    </ul>


                </div>

                <label class="consent-checkbox-row" for="privacyConsent" style="margin-top: 18px;">
                    <input type="checkbox" id="privacyConsent" name="privacy_consent" value="1">
                    <span class="consent-checkbox-control" aria-hidden="true"></span>
                    <span class="consent-checkbox-text">I have read this notice and agree to the identity verification and processing described above.</span>
                </label>
            </div>

            <div class="select-type-form" style="margin-top: 20px;">
                <a href="{{ route('visitor.upload_document') }}" id="continueBtn" class="btn btn-primary btn-large btn-continue-initial form-width-100 disabled-link" style="text-decoration: none;">Agree and continue</a>
            </div>
            
            <div class="verification-assurance">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <span>secure multimodal document extraction</span>
            </div>
        </div>
        
        <!-- Animated SVG graphic on the right -->
        <div class="hero-visual">
            <img src="{{ asset('img/hero.png') }}" alt="" class="hero-image">
        </div>
    </section>

    <script>
        const consentCheckbox = document.getElementById('privacyConsent');
        const continueBtn = document.getElementById('continueBtn');

        function updateBtnState() {
            if (consentCheckbox.checked) {
                continueBtn.classList.remove('disabled-link');
                continueBtn.removeAttribute('aria-disabled');
            } else {
                continueBtn.classList.add('disabled-link');
                continueBtn.setAttribute('aria-disabled', 'true');
            }
        }

        consentCheckbox.addEventListener('change', updateBtnState);
        updateBtnState();

        continueBtn.addEventListener('click', function(e) {
            if (this.classList.contains('disabled-link')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
