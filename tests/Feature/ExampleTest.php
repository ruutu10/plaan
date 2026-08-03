<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'));
    }

    public function test_landing_page_links_to_login_for_technicians()
    {
        $this->get(route('home'))->assertOk();

        $this->get(route('login'))->assertOk();
    }
}
