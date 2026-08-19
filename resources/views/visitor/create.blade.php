<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Registration — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page">
    @include('layouts.site-header')
    <main class="registration-shell">
        <div class="registration-background" aria-hidden="true">
            <div class="registration-background-glow"></div>
            <div class="registration-background-art">
                <img src="{{ asset('img/hero.png') }}" alt="" class="hero-image">
            </div>
            <span class="registration-accent registration-accent-lime"></span>
            <span class="registration-accent registration-accent-coral"></span>
        </div>

        <section class="registration-card" aria-labelledby="registration-title">
            <div class="registration-heading">
                <span class="tagline no-margin">VERIFIED IDENTITY</span>
                <h1 id="registration-title" class="headline">Complete your details<span class="dot">.</span></h1>
                @include('visitor.partials.selected-registration-day')
            </div>

            @if($errors->has('verification') || $errors->has('registration'))
                <div class="form-error-msg" role="alert" style="margin: 0 0 18px; text-align: center;">{{ $errors->first('verification') ?: $errors->first('registration') }}</div>
            @endif

            @php($entranceFee = data_get(session('event_registration_day', []), 'entrance_fee', data_get($category, 'entrance_fee')))

            <form method="POST" action="{{ route('visitor.confirm') }}" class="registration-form">
                @csrf
                <input type="hidden" name="document_type" value="{{ $type }}">

                <div class="registration-grid">
                    <div class="form-group form-group-wide">
                        <label for="full_name" class="form-label-premium">Full Name</label>
                        <input id="full_name" name="full_name" class="form-control-premium @error('full_name') is-invalid @enderror" value="{{ old('full_name', data_get($verification, 'full_name')) }}" required>
                        @error('full_name')<span class="form-error-msg">{{ $message }}</span>@enderror
                    </div>

                    @if($type === 'nic')
                        <div class="form-group form-group-wide">
                            <label class="form-label-premium" for="name_confirmation">
                                <input id="name_confirmation" name="name_confirmation" type="checkbox" value="1" @checked(old('name_confirmation')) required>
                                I confirm that the English spelling of my name is correct.
                            </label>
                            @error('name_confirmation')<span class="form-error-msg">{{ $message }}</span>@enderror
                        </div>
                    @endif

                    <div class="form-group">
                        <label for="document_number" class="form-label-premium">
                            {{ $type === 'passport' ? 'Passport Number' : 'NIC Number' }}
                        </label>
                        <input id="document_number" name="document_number" class="form-control-premium @error('document_number') is-invalid @enderror" value="{{ old('document_number', data_get($verification, 'document_number')) }}" required>
                        @if($type === 'driving_license' && data_get($verification, 'driving_license_number'))
                            <span class="field-microcopy">NIC read from field 4c · Licence {{ data_get($verification, 'driving_license_number') }}</span>
                        @endif
                        @error('document_number')<span class="form-error-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="entrance_fee" class="form-label-premium">Entrance Fee</label>
                        <input id="entrance_fee" class="form-control-premium form-control-readonly" value="{{ $entranceFee !== null ? 'LKR '.number_format((float) $entranceFee, 2) : 'Not assigned' }}" readonly>
                        @if(data_get($category, 'name'))<span class="field-microcopy">{{ data_get($category, 'name') }} category</span>@endif
                    </div>

                    <div class="form-group form-group-wide">
                        <label for="address" class="form-label-premium">Address</label>
                        <textarea id="address" name="address" class="form-control-premium registration-address @error('address') is-invalid @enderror" required>{{ old('address', data_get($verification, 'address')) }}</textarea>
                        @error('address')<span class="form-error-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="mobile_number" class="form-label-premium">Mobile Number</label>
                        <div class="phone-control @error('mobile_number') is-invalid @enderror">
                            <span>+94</span>
                            <input id="mobile_number" name="mobile_number" type="tel" inputmode="numeric" maxlength="9" value="{{ old('mobile_number') }}" required autocomplete="tel">
                        </div>
                        @error('mobile_number')<span class="form-error-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <div class="field-label-row">
                            <label for="whatsapp_number" class="form-label-premium">WhatsApp Number</label>
                            <label class="same-number-label"><input id="same_as_mobile" name="same_as_mobile" type="checkbox" value="1" @checked(old('same_as_mobile'))> Same as Mobile</label>
                        </div>
                        <div class="phone-control @error('whatsapp_number') is-invalid @enderror">
                            <span>+94</span>
                            <input id="whatsapp_number" name="whatsapp_number" type="tel" inputmode="numeric" maxlength="9" value="{{ old('whatsapp_number') }}" required autocomplete="tel">
                        </div>
                        @error('whatsapp_number')<span class="form-error-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="occupation" class="form-label-premium">Occupation</label>
                        <input id="occupation" class="form-control-premium @error('occupation') is-invalid @enderror" name="occupation" value="{{ old('occupation') }}" required>
                        @error('occupation')<span class="form-error-msg">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="company" class="form-label-premium">Company</label>
                        <input id="company" class="form-control-premium @error('company') is-invalid @enderror" name="company" value="{{ old('company') }}" required>
                        @error('company')<span class="form-error-msg">{{ $message }}</span>@enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-large registration-next">Next</button>
            </form>
        </section>

        <footer class="registration-trust">Your verified identity details are encrypted and used only to complete this visit.</footer>
    </main>

    <script>
        const mobile = document.getElementById('mobile_number');
        const whatsapp = document.getElementById('whatsapp_number');
        const sameAsMobile = document.getElementById('same_as_mobile');

        function syncWhatsApp() {
            whatsapp.disabled = sameAsMobile.checked;
            whatsapp.required = !sameAsMobile.checked;
            if (sameAsMobile.checked) whatsapp.value = mobile.value;
        }

        sameAsMobile.addEventListener('change', syncWhatsApp);
        mobile.addEventListener('input', () => {
            mobile.value = mobile.value.replace(/\D/g, '').slice(0, 9);
            if (sameAsMobile.checked) whatsapp.value = mobile.value;
        });
        whatsapp.addEventListener('input', () => whatsapp.value = whatsapp.value.replace(/\D/g, '').slice(0, 9));
        syncWhatsApp();
    </script>
</body>
</html>
