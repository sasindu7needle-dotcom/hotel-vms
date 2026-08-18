<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}?v={{ filemtime(public_path('favicon.png')) }}">
    <title>Visitor Management — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page welcome-page">
    @include('layouts.site-header')
    <main class="hero">
        <div class="hero-content">
            <div class="tagline">VISION 2030</div>
            <h1 class="headline">INTERNATIONAL HOSPITALITY LEADERS' CONFERENCE<span class="dot">.</span></h1>
            <p class="description">
                Shaping the Future Performance of the Hospitality Industry.
            </p>
            <div class="cta-buttons">
                <a href="{{ route('visitor.start') }}" class="btn btn-primary btn-large">Register</a>
            </div>
        </div>

        <div class="hero-visual" aria-hidden="true">
            <img src="{{ asset('img/hero.png') }}" alt="" class="hero-image">
        </div>
    </main>

    <section class="conference-icons" aria-label="Conference themes">
        <div class="conference-icon-viewport">
            <ul class="conference-icon-grid">
                @foreach (['Travel', 'Innovation', 'Sustainability', 'Leadership', 'Growth', 'Excellence', 'Investment'] as $index => $theme)
                    <li class="conference-icon-item" style="--icon-index: {{ $index }}">
                        <span class="conference-icon-orbit">
                            <img src="{{ asset('img/icon/icon ' . ($index + 1) . '.png') }}" alt="{{ $theme }}">
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <script>
        (() => {
            const viewport = document.querySelector('.conference-icon-viewport');
            const grid = document.querySelector('.conference-icon-grid');
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

            if (!viewport || !grid) {
                return;
            }

            const originalIcons = Array.from(grid.children);
            if (!reducedMotion.matches) {
                originalIcons.forEach((icon) => {
                    const duplicate = icon.cloneNode(true);
                    duplicate.setAttribute('aria-hidden', 'true');
                    grid.appendChild(duplicate);
                });
            }

            let queueAnimation;
            let resizeFrame;

            const startQueue = () => {
                queueAnimation?.cancel();

                const gap = parseFloat(window.getComputedStyle(grid).columnGap) || 0;
                const visibleSlots = parseFloat(
                    window.getComputedStyle(viewport).getPropertyValue('--conference-visible-slots')
                ) || 7;
                const slotWidth = Math.max(
                    1,
                    (viewport.clientWidth - (gap * (visibleSlots - 1))) / visibleSlots
                );
                const loopDistance = (slotWidth + gap) * 7;

                grid.style.setProperty('--conference-icon-slot', `${slotWidth}px`);

                if (reducedMotion.matches) {
                    return;
                }

                queueAnimation = grid.animate([
                    { transform: 'translateX(0)' },
                    { transform: `translateX(-${loopDistance}px)` }
                ], {
                    duration: 10500,
                    iterations: Infinity,
                    easing: 'linear'
                });
            };

            const resizeObserver = new ResizeObserver(() => {
                window.cancelAnimationFrame(resizeFrame);
                resizeFrame = window.requestAnimationFrame(startQueue);
            });

            resizeObserver.observe(viewport);
            startQueue();
        })();
    </script>
</body>
</html>
