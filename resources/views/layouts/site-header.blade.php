<style>
    .site-page-logo {
        position: absolute;
        top: clamp(16px, 2.5vw, 28px);
        left: 50%;
        z-index: 1000;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: min(260px, 62vw);
        line-height: 0;
        transform: translateX(-50%);
    }

    .site-page-logo img {
        display: block;
        width: 100%;
        height: auto;
        max-height: 58px;
        object-fit: contain;
    }

    @media (max-width: 600px) {
        .site-page-logo {
            top: 14px;
            width: min(220px, 66vw);
        }

        .site-page-logo img {
            max-height: 48px;
        }

        body.landing-page .site-page-logo + .hero,
        body.landing-page .site-page-logo + .registration-shell {
            padding-top: 88px;
        }

        body.landing-page .site-page-logo + .registration-shell {
            justify-content: flex-start;
        }
    }

    @media print {
        .site-page-logo { display: none !important; }
    }
</style>
<a href="{{ url('/') }}" class="site-page-logo" aria-label="Institute of Hospitality home">
    <img src="{{ asset('img/logo.png') }}" alt="Institute of Hospitality">
</a>
