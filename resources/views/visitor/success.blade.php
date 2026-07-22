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
            <svg viewBox="0 0 900 650" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Ground Slab -->
                <path d="M0 450 L450 650 L900 450 L450 250 Z" fill="#e8e8e8"/>
                <path d="M0 450 L450 650 L900 450" fill="none" stroke="#d0d0d0" stroke-width="1"/>
                <path d="M200 520 L450 640 L700 520 L700 480 L450 600 L200 480 Z" fill="#1a1a1a"/>
                
                <!-- Pod 1: Back-left -->
                <g class="bobbing-pod-1">
                    <g transform="translate(180, 80)">
                        <path d="M0 80 L100 130 L200 80 L100 30 Z" fill="#c8e063"/>
                        <path d="M0 80 L100 30 L100 0 L0 50 Z" fill="#2a2a2a"/>
                        <path d="M100 130 L200 80 L200 50 L100 100 Z" fill="#1f1f1f"/>
                        <path d="M100 30 L100 80 L130 65 L130 15 Z" fill="#e85d5d"/>
                        <circle cx="60" cy="70" r="5" fill="#111"/>
                        <rect x="55" y="75" width="10" height="18" rx="2" fill="#111"/>
                        <circle cx="90" cy="85" r="5" fill="#111"/>
                        <rect x="85" y="90" width="10" height="18" rx="2" fill="#111"/>
                        <circle cx="130" cy="60" r="5" fill="#e85d5d"/>
                        <rect x="125" y="65" width="10" height="18" rx="2" fill="#e85d5d"/>
                        <path d="M40 85 L80 105 L100 95 L60 75 Z" fill="#fff" opacity="0.9"/>
                        <path d="M40 85 L40 95 L60 105 L60 95 Z" fill="#ddd"/>
                    </g>
                </g>
                
                <!-- Pod 2: Center-left -->
                <g class="bobbing-pod-2">
                    <g transform="translate(420, 160)">
                        <path d="M0 100 L140 170 L280 100 L140 30 Z" fill="#c8e063"/>
                        <path d="M0 100 L140 30 L140 0 L0 70 Z" fill="#2a2a2a"/>
                        <path d="M140 170 L280 100 L280 70 L140 140 Z" fill="#1f1f1f"/>
                        <path d="M140 30 L140 100 L175 82 L175 12 Z" fill="#e85d5d"/>
                        <path d="M180 60 L220 40 L220 55 L180 75 Z" fill="#4ecdc4"/>
                        <circle cx="80" cy="95" r="6" fill="#111"/>
                        <rect x="74" y="101" width="12" height="22" rx="2" fill="#111"/>
                        <circle cx="120" cy="115" r="6" fill="#111"/>
                        <rect x="114" y="121" width="12" height="22" rx="2" fill="#111"/>
                        <circle cx="200" cy="75" r="6" fill="#e85d5d"/>
                        <rect x="194" y="81" width="12" height="22" rx="2" fill="#e85d5d"/>
                        <circle cx="230" cy="60" r="6" fill="#111"/>
                        <rect x="224" y="66" width="12" height="22" rx="2" fill="#111"/>
                        <path d="M60 105 L75 112 L70 125 L55 118 Z" fill="#888"/>
                        <path d="M100 125 L115 132 L110 145 L95 138 Z" fill="#888"/>
                    </g>
                </g>
                
                <!-- Pod 3: Back-right -->
                <g class="bobbing-pod-3">
                    <g transform="translate(620, 100)">
                        <path d="M0 60 L70 95 L140 60 L70 25 Z" fill="#c8e063"/>
                        <path d="M0 60 L70 25 L70 5 L0 40 Z" fill="#2a2a2a"/>
                        <path d="M70 95 L140 60 L140 40 L70 75 Z" fill="#1f1f1f"/>
                        <path d="M70 25 L70 80 L95 67 L95 12 Z" fill="#e85d5d"/>
                        <circle cx="50" cy="70" r="5" fill="#111"/>
                        <rect x="45" y="75" width="10" height="16" rx="2" fill="#111"/>
                        <circle cx="100" cy="50" r="5" fill="#111"/>
                        <rect x="95" y="55" width="10" height="16" rx="2" fill="#111"/>
                    </g>
                </g>
                
                <!-- Pod 4: Front-right -->
                <g class="bobbing-pod-4">
                    <g transform="translate(580, 280)">
                        <path d="M0 80 L100 130 L200 80 L100 30 Z" fill="#c8e063"/>
                        <path d="M0 80 L100 30 L100 5 L0 55 Z" fill="#2a2a2a"/>
                        <path d="M100 130 L200 80 L200 55 L100 105 Z" fill="#1f1f1f"/>
                        <path d="M100 30 L100 90 L130 75 L130 15 Z" fill="#e85d5d"/>
                        <circle cx="70" cy="85" r="5" fill="#111"/>
                        <rect x="65" y="90" width="10" height="14" rx="2" fill="#111"/>
                        <path d="M55 100 L75 110 L70 120 L50 110 Z" fill="#e85d5d"/>
                        <circle cx="140" cy="65" r="5" fill="#111"/>
                        <rect x="135" y="70" width="10" height="14" rx="2" fill="#111"/>
                        <path d="M125 80 L145 90 L140 100 L120 90 Z" fill="#e85d5d"/>
                        <path d="M80 95 L120 115 L140 105 L100 85 Z" fill="#fff" opacity="0.9"/>
                    </g>
                </g>
                
                <!-- Pod 5: Far-right -->
                <g class="bobbing-pod-5">
                    <g transform="translate(760, 200)">
                        <path d="M0 80 L50 105 L100 80 L50 55 Z" fill="#c8e063"/>
                        <path d="M0 80 L50 55 L50 35 L0 60 Z" fill="#2a2a2a"/>
                        <path d="M50 105 L100 80 L100 60 L50 85 Z" fill="#1f1f1f"/>
                        <circle cx="55" cy="80" r="5" fill="#111"/>
                        <rect x="50" y="85" width="10" height="16" rx="2" fill="#111"/>
                    </g>
                </g>
                
                <!-- Ground Characters -->
                <g class="ground-visitors">
                    <circle cx="120" cy="480" r="6" fill="#111"/>
                    <rect x="114" y="486" width="12" height="24" rx="2" fill="#111"/>
                    <circle cx="180" cy="510" r="6" fill="#111"/>
                    <rect x="174" y="516" width="12" height="24" rx="2" fill="#111"/>
                    
                    <!-- Pulsing checkin badge -->
                    <g class="pulsing-badge">
                        <circle cx="220" cy="530" r="6" fill="#111"/>
                        <rect x="214" y="536" width="12" height="24" rx="2" fill="#111"/>
                        <rect x="226" y="542" width="10" height="14" rx="2" fill="#e85d5d" opacity="0.8"/>
                    </g>
                    
                    <circle cx="380" cy="420" r="6" fill="#e85d5d"/>
                    <rect x="374" y="426" width="12" height="24" rx="2" fill="#e85d5d"/>
                    <circle cx="340" cy="460" r="6" fill="#111"/>
                    <rect x="334" y="466" width="12" height="24" rx="2" fill="#111"/>
                </g>
                
                <!-- Ground Desk Checkpoint -->
                <g transform="translate(280, 500)">
                    <path d="M0 10 L40 30 L80 10 L40 -10 Z" fill="#111"/>
                    <path d="M5 10 L40 27 L40 20 L5 3 Z" fill="#333"/>
                    <path d="M40 27 L75 10 L75 3 L40 20 Z" fill="#222"/>
                    <circle cx="20" cy="22" r="5" fill="#e85d5d"/>
                    <circle cx="60" cy="12" r="5" fill="#e85d5d"/>
                    <path d="M20 0 L40 -10 L60 0 L40 10 Z" fill="#fff" opacity="0.9"/>
                </g>
            </svg>
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
