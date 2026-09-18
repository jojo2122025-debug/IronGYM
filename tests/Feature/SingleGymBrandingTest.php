<?php

namespace Tests\Feature;

use Tests\TestCase;

class SingleGymBrandingTest extends TestCase
{
    public function test_home_page_uses_single_gym_branding(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('IronGYM');
        $response->assertDontSee('SaaS');
    }

    public function test_portal_routes_redirect_to_dashboard(): void
    {
        $response = $this->get('/portal');
        $response->assertRedirect('/dashboard');

        $loginResponse = $this->get('/portal/login');
        $loginResponse->assertRedirect('/dashboard');
    }

    public function test_dashboard_includes_daily_checkin_log_toggle(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('toggle-checkin-log-btn');
        $response->assertSee('سجل الدخول والخروج');
    }
}
