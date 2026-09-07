<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetaPixelTest extends TestCase
{
    public function test_public_campaign_pages_include_the_consent_gated_meta_pixel(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('https://connect.facebook.net/en_US/fbevents.js', false)
            ->assertSee("fbq('init', '2237997223685052');", false)
            ->assertSee("fbq('track', 'PageView');", false)
            ->assertSee("window.localStorage.getItem('hifCookieConsent.v3') === 'accepted'", false)
            ->assertDontSee('https://www.facebook.com/tr?id=2237997223685052', false);
    }

    public function test_landing_page_includes_cookie_privacy_choices(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="cookie-consent-banner"', false)
            ->assertSee('Your Privacy Choices')
            ->assertSee('Accept All')
            ->assertSee('Reject')
            ->assertSee('Manage cookies')
            ->assertSee('data-cookie-consent-required', false)
            ->assertSee('class="cookie-banner"', false)
            ->assertSee('style="display: none;"', false);
    }

    public function test_cookie_banner_is_limited_to_the_landing_page(): void
    {
        $this->get('/visitor/new')
            ->assertDontSee('id="cookie-consent-banner"', false);
    }

    public function test_gate_terminal_does_not_generate_campaign_page_views(): void
    {
        $this->get('/gate/A/in')
            ->assertOk()
            ->assertDontSee('2237997223685052', false);
    }

    public function test_admin_pages_do_not_generate_campaign_page_views(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('2237997223685052', false);
    }
}
