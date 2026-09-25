<?php

namespace Tests\Feature;

use Tests\TestCase;

class ThemeSettingsTest extends TestCase
{
    public function test_dashboard_exposes_five_accessible_appearance_choices(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-view="settings"', false)
            ->assertSee('id="view-settings"', false)
            ->assertSee('data-theme-option="default"', false)
            ->assertSee('data-theme-option="ocean"', false)
            ->assertSee('data-theme-option="royal"', false)
            ->assertSee('data-theme-option="sand"', false)
            ->assertSee('data-theme-option="frost"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('irongym_theme_v1');
    }
}
