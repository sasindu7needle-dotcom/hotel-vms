<style>
    .site-header.site-header {
        position: relative;
        z-index: 1000;
        display: flex;
        align-items: center;
        width: 100%;
        min-height: 78px;
        padding: 10px clamp(18px, 4vw, 56px);
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
    }

    .site-header-logo {
        display: inline-flex;
        align-items: center;
        line-height: 0;
    }

    .site-header-logo img {
        display: block;
        width: auto;
        height: 56px;
        max-width: min(72vw, 330px);
        object-fit: contain;
    }

    .site-header + .hero,
    .site-header + .registration-shell,
    .site-header + .admin-login-shell,
    .site-header + .admin-dashboard-shell,
    .site-header + .exhibitor-dashboard-shell,
    .site-header + .terminal-shell {
        min-height: calc(100vh - 78px);
        min-height: calc(100dvh - 78px);
    }

    @media (max-width: 600px) {
        .site-header.site-header {
            min-height: 66px;
            padding: 8px 16px;
        }

        .site-header-logo img {
            height: 46px;
            max-width: 78vw;
        }

        .site-header + .hero,
        .site-header + .registration-shell,
        .site-header + .admin-login-shell,
        .site-header + .admin-dashboard-shell,
        .site-header + .exhibitor-dashboard-shell,
        .site-header + .terminal-shell {
            min-height: calc(100vh - 66px);
            min-height: calc(100dvh - 66px);
        }

    }

    @media print {
        .site-header { display: none !important; }
    }
</style>
<header class="site-header">
    <a href="{{ url('/') }}" class="site-header-logo" aria-label="Institute of Hospitality home">
        <img src="{{ asset('img/logo.png') }}" alt="Institute of Hospitality">
    </a>
</header>
