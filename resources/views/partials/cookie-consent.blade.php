<style>
    body.landing-page .privacy-consent-required[aria-disabled="true"] {
        opacity: 0.55;
        cursor: not-allowed;
        pointer-events: none;
    }

    body.landing-page .cookie-banner {
        position: fixed;
        bottom: 0;
        left: 0;
        z-index: 9999;
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        padding: 24px 48px;
        box-sizing: border-box;
        color: #fff;
        background-color: #111827;
        box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    body.landing-page .cookie-content {
        max-width: 60%;
    }

    body.landing-page .cookie-subtitle {
        display: block;
        margin-bottom: 8px;
        color: #fde047;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    body.landing-page .cookie-title {
        margin: 0 0 12px;
        color: #fff;
        font-size: 1.75rem;
        font-weight: 700;
        line-height: normal;
    }

    body.landing-page .cookie-desc {
        margin: 0;
        color: #d1d5db;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    body.landing-page .cookie-desc a {
        color: #fde047;
        text-decoration: underline;
    }

    body.landing-page .cookie-desc a:hover {
        text-decoration: none;
    }

    body.landing-page .cookie-buttons {
        display: flex;
        flex-shrink: 0;
        gap: 12px;
    }

    body.landing-page .cookie-banner .btn {
        display: inline-block;
        min-width: 0;
        padding: 12px 24px;
        border: none;
        border-radius: 4px;
        font-size: 0.9rem;
        font-weight: 700;
        line-height: normal;
        text-align: center;
        white-space: nowrap;
        cursor: pointer;
        transition: opacity 0.2s, background-color 0.2s;
    }

    body.landing-page .cookie-banner .btn:hover {
        opacity: 0.9;
    }

    body.landing-page .cookie-banner .btn:focus-visible,
    body.landing-page .cookie-desc a:focus-visible {
        outline: 3px solid #93c5fd;
        outline-offset: 3px;
    }

    body.landing-page .cookie-banner .btn-accept {
        color: #111827;
        background-color: #fde047;
    }

    body.landing-page .cookie-banner .btn-reject {
        color: #111827;
        background-color: #fff;
    }

    body.landing-page .cookie-banner .btn-manage {
        border: 1px solid #4b5563;
        color: #fff;
        background-color: transparent;
    }

    body.landing-page .cookie-banner .btn-manage:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    @media (max-width: 900px) {
        body.landing-page .cookie-banner {
            flex-direction: column;
            align-items: flex-start;
            gap: 24px;
            padding: 24px;
        }

        body.landing-page .cookie-content {
            max-width: 100%;
        }

        body.landing-page .cookie-buttons {
            width: 100%;
            flex-wrap: wrap;
        }

        body.landing-page .cookie-banner .btn {
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
    }
</style>

<div
    id="cookie-consent-banner"
    class="cookie-banner"
    role="region"
    aria-labelledby="cookie-consent-title"
    aria-describedby="cookie-consent-description"
    style="display: none;"
>
    <div class="cookie-content">
        <span class="cookie-subtitle">Your Privacy Choices</span>
        <h2 id="cookie-consent-title" class="cookie-title">Cookies on this website</h2>
        <p id="cookie-consent-description" class="cookie-desc">
            We use necessary storage to operate the website. With your permission, we also load analytics and external media
            such as YouTube and Facebook. <a id="cookie-policy-link" href="#">Read our Cookie Policy</a>.
        </p>
    </div>
    <div class="cookie-buttons" aria-label="Cookie consent options">
        <button id="btn-accept" class="btn btn-accept" type="button">Accept All</button>
        <button id="btn-reject" class="btn btn-reject" type="button">Reject</button>
        <button id="btn-manage" class="btn btn-manage" type="button">Manage cookies</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const consentKey = 'hifCookieConsent.v3';
        const banner = document.getElementById('cookie-consent-banner');
        const acceptButton = document.getElementById('btn-accept');
        const rejectButton = document.getElementById('btn-reject');
        const manageButton = document.getElementById('btn-manage');
        const policyLink = document.getElementById('cookie-policy-link');
        const protectedActions = document.querySelectorAll('[data-cookie-consent-required]');

        if (!banner || !acceptButton || !rejectButton || !manageButton || !policyLink) {
            return;
        }

        const readConsent = () => {
            try {
                return window.localStorage.getItem(consentKey);
            } catch (error) {
                return null;
            }
        };

        const setProtectedActionsEnabled = (enabled) => {
            protectedActions.forEach((action) => {
                action.setAttribute('aria-disabled', String(!enabled));
                action.classList.toggle('privacy-consent-required', !enabled);
            });
        };

        const savedConsent = readConsent();
        const hasSavedChoice = savedConsent === 'accepted' || savedConsent === 'rejected';

        setProtectedActionsEnabled(hasSavedChoice);

        if (!hasSavedChoice) {
            banner.style.display = 'flex';
        }

        const saveConsent = (choice) => {
            try {
                window.localStorage.setItem(consentKey, choice);
            } catch (error) {
                // The choice still applies to the current page when storage is unavailable.
            }

            setProtectedActionsEnabled(true);
            banner.style.display = 'none';

            if (choice === 'accepted') {
                window.dispatchEvent(new CustomEvent('cookie-consent:accepted'));
            }
        };

        acceptButton.addEventListener('click', () => saveConsent('accepted'));
        rejectButton.addEventListener('click', () => saveConsent('rejected'));
        manageButton.addEventListener('click', () => policyLink.click());
        policyLink.addEventListener('click', (event) => event.preventDefault());
    });
</script>
