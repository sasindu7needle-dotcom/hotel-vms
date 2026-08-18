<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
</body>
</html>
