<?php

namespace Tests\Feature;

use Tests\TestCase;

class SingleGymBrandingTest extends TestCase
{
    public function test_root_page_is_the_login_screen(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('form-login');
        $response->assertSee('IRONGYM');
    }

    public function test_retired_portal_routes_are_not_available(): void
    {
        $this->get('/portal')->assertNotFound();
        $this->get('/portal/login')->assertNotFound();
    }

    public function test_old_dashboard_url_redirects_to_login_root(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
    }
}
