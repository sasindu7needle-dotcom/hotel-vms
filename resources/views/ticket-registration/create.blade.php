<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Registration &mdash; Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page manual-registration-flow">
    @include('layouts.site-header')
    <main class="registration-shell">
        <div class="registration-background" aria-hidden="true">
            <div class="registration-background-glow"></div>
            <div class="registration-background-art"><img src="{{ asset('img/hero.png') }}" alt="" class="hero-image"></div>
            <span class="registration-accent registration-accent-lime"></span>
            <span class="registration-accent registration-accent-coral"></span>
        </div>

        <section class="registration-card" aria-labelledby="ticket-registration-title">
            <div class="registration-heading">
                <span class="tagline no-margin">TICKET DESK</span>
                <h1 id="ticket-registration-title" class="headline">Ticket registration<span class="dot">.</span></h1>
                <p>Enter the ticket holder's details and upload a clear profile photo to issue their entrance card.</p>
            </div>

            @if($errors->any())
                <div class="manual-flow-errors" role="alert">Please correct the highlighted fields and try again.</div>
            @endif

            <form method="POST" action="{{ route('ticket-registration.store') }}" enctype="multipart/form-data" class="registration-form">
                @csrf
                <fieldset class="manual-flow-stage">
                    <legend><span>1</span><strong>Ticket holder details</strong><small>Contact and work information</small></legend>
                    <div class="registration-grid">
                        <div class="form-group form-group-wide">
                            <label class="form-label-premium" for="ticket-name">Name</label>
                            <input id="ticket-name" class="form-control-premium @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required maxlength="180" autocomplete="name" autofocus placeholder="e.g. Kasun Perera">
                            @error('name')<span class="form-error-msg">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label-premium" for="ticket-designation">Designation</label>
                            <input id="ticket-designation" class="form-control-premium @error('designation') is-invalid @enderror" name="designation" value="{{ old('designation') }}" required maxlength="100" placeholder="e.g. Director">
                            @error('designation')<span class="form-error-msg">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label-premium" for="ticket-company">Company</label>
                            <input id="ticket-company" class="form-control-premium @error('company') is-invalid @enderror" name="company" value="{{ old('company') }}" required maxlength="150" autocomplete="organization" placeholder="e.g. HIF Sri Lanka">
                            @error('company')<span class="form-error-msg">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label-premium" for="ticket-whatsapp">WhatsApp Number</label>
                            <input id="ticket-whatsapp" class="form-control-premium @error('whatsapp_number') is-invalid @enderror" name="whatsapp_number" value="{{ old('whatsapp_number') }}" required inputmode="tel" autocomplete="tel" placeholder="+94771234567">
                            @error('whatsapp_number')<span class="form-error-msg">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label-premium" for="ticket-email">Email</label>
                            <input id="ticket-email" class="form-control-premium @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" required maxlength="100" autocomplete="email" placeholder="visitor@example.com">
                            @error('email')<span class="form-error-msg">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group form-group-wide">
                            <label class="form-label-premium" for="ticket-number">Ticket Number</label>
                            <input id="ticket-number" class="form-control-premium @error('ticket_number') is-invalid @enderror" name="ticket_number" value="{{ old('ticket_number') }}" required maxlength="100" autocomplete="off" spellcheck="false" placeholder="e.g. HIF-2026-00125">
                            <small class="field-microcopy">Each ticket number can be registered only once.</small>
                            @error('ticket_number')<span class="form-error-msg">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="manual-flow-stage">
                    <legend><span>2</span><strong>Profile image</strong><small>Used on the visitor's entrance card</small></legend>
                    <div class="manual-flow-upload-grid">
                        <label class="manual-flow-upload">
                            <input id="ticket-profile-image" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" required>
                            <strong>Ticket holder profile image</strong>
                            <small id="ticket-profile-image-name">Choose a clear front-facing photo</small>
                        </label>
                    </div>
                    @error('profile_image')<span class="form-error-msg">{{ $message }}</span>@enderror
                </fieldset>

                <div class="manual-flow-actions">
                    <a class="manual-flow-cancel" href="{{ url('/') }}">Cancel</a>
                    <button class="btn btn-primary btn-large registration-next" type="submit">Register ticket holder &rarr;</button>
                </div>
            </form>
        </section>
        <footer class="registration-trust">The profile image is stored securely and is available only to authorized event staff.</footer>
    </main>
    <script>
        const profileImage = document.getElementById('ticket-profile-image');
        const profileImageName = document.getElementById('ticket-profile-image-name');
        profileImage.addEventListener('change', () => {
            profileImageName.textContent = profileImage.files[0]?.name || 'Choose a clear front-facing photo';
        });
    </script>
</body>
</html>
