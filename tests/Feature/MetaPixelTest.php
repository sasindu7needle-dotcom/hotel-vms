<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetaPixelTest extends TestCase
{
    public function test_public_campaign_pages_include_the_meta_pixel(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('https://connect.facebook.net/en_US/fbevents.js', false)
            ->assertSee("fbq('init', '2237997223685052');", false)
            ->assertSee("fbq('track', 'PageView');", false)
            ->assertSee('https://www.facebook.com/tr?id=2237997223685052&amp;ev=PageView&amp;noscript=1', false);
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
