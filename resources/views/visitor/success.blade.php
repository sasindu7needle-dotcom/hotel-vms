<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Success — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page">

    <section class="hero">
        <div class="hero-content hero-content-center">
            
            <!-- Success Icon with Pulse Ring -->
            <div class="success-animation-container">
                <div class="pulse-ring"></div>
                <div class="success-circle">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1a1a1a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
            </div>

            <div class="tagline">VERIFIED IDENTITY</div>
            <h1 class="headline headline-success">Identity Confirmed<span class="dot dot-theme">.</span></h1>
            
            <p class="description text-center">
                Your identity document has been verified. You will be redirected shortly to complete your check-in details.
            </p>

            <a href="{{ route('visitor.create', ['type' => $type, 'verified' => 'true']) }}" class="btn btn-primary btn-large btn-success-action">
                Complete Registration (<span id="countdown">5</span>s)
            </a>
            
        </div>
        
        <!-- Animated SVG graphic on the right -->
        <div class="hero-visual">
            <img src="{{ asset('img/hero.png') }}" alt="" class="hero-image">
        </div>
    </section>

    <style>
        @keyframes success-pulse {
            0% {
                transform: scale(0.9);
                opacity: 1;
            }
            100% {
                transform: scale(1.4);
                opacity: 0;
            }
        }
    </style>

    <script>
        let count = 5;
        const countdownEl = document.getElementById('countdown');
        const targetUrl = "{{ route('visitor.create', ['type' => $type, 'verified' => 'true']) }}";
        
        const interval = setInterval(() => {
            count--;
            countdownEl.innerText = count;
            if (count <= 0) {
                clearInterval(interval);
                window.location.href = targetUrl;
            }
        }, 1000);
    </script>
</body>
</html>
