<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Login — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page admin-login-page">
    <main class="admin-login-shell">
        <div class="admin-login-art" aria-hidden="true">@include('visitor.partials.checkin-illustration')</div>
        <section class="admin-login-card" aria-labelledby="superadmin-login-title">
            <a href="{{ url('/') }}" class="admin-brand"><span class="admin-brand-mark"></span><span>TRACTION <strong>GUEST</strong></span></a>
            <div class="admin-login-heading">
                <span class="tagline no-margin" style="background:#fee2e2; color:#dc2626; border-color:#fca5a5;">SUPERADMIN PORTAL</span>
                <h1 id="superadmin-login-title" class="headline">Superadmin Access<span class="dot">.</span></h1>
                <p>Sign in with elevated credentials to manage system master controls.</p>
            </div>

            @if(session('status'))<div class="admin-auth-alert admin-auth-success">{{ session('status') }}</div>@endif
            @error('authentication')<div class="admin-auth-alert">{{ $message }}</div>@enderror

            <form method="POST" action="{{ route('superadmin.login.submit') }}" class="admin-login-form">
                @csrf
                <div class="form-group">
                    <label for="username" class="form-label-premium">Superadmin Username</label>
                    <div class="admin-input-wrap @error('username') is-invalid @enderror">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zM9 7a3 3 0 0 1 6 0v3H9V7z"></path></svg>
                        <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus placeholder="Enter superadmin username">
                    </div>
                    @error('username')<span class="form-error-msg">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label for="password" class="form-label-premium">Master Password</label>
                    <div class="admin-input-wrap @error('password') is-invalid @enderror">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>
                        <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••••••">
                        <button id="togglePassword" type="button" class="password-toggle" aria-label="Show password">Show</button>
                    </div>
                    @error('password')<span class="form-error-msg">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="btn btn-primary btn-large admin-login-button" style="background:#111827; border-color:#111827; color:#fff;">Sign In as Superadmin</button>
            </form>
            <p class="admin-login-trust"><span></span> Superadmin Elevated Session · Encrypted Authentication</p>
        </section>
    </main>
    <script>
        const toggle = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        toggle.addEventListener('click', () => {
            const showing = password.type === 'text';
            password.type = showing ? 'password' : 'text';
            toggle.textContent = showing ? 'Show' : 'Hide';
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    </script>
</body>
</html>
