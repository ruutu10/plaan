<?php

namespace Tests\Feature;

use App\Enums\SignupSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class AuthentikSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.authentik.base_url' => 'https://sso.example.test',
            'services.authentik.client_id' => 'test-client-id',
            'services.authentik.client_secret' => 'test-client-secret',
            'services.authentik.redirect' => 'http://localhost/auth/authentik/callback',
        ]);
    }

    public function test_a_guest_visiting_login_is_silently_redirected_to_authentik(): void
    {
        // Deliberately not using Socialite::fake() here: its FakeProvider
        // always returns a fixed stub URL regardless of with(), so it can't
        // prove prompt=none was actually attached. AbstractProvider::redirect()
        // never makes a network call — it only builds a URL — so letting the
        // real Authentik provider run is safe and lets us assert on it.
        $response = $this->get(route('login'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('sso.example.test', $location);
        $this->assertStringContainsString('prompt=none', $location);
    }

    public function test_a_silent_redirect_reached_via_an_inertia_visit_uses_location_not_a_plain_redirect(): void
    {
        // Reaching /tehnikaplaan via an in-app Inertia <Link> makes this
        // request an XHR, not a full page navigation. A plain 302 here would
        // have the browser's fetch() auto-follow it straight into a
        // cross-origin CORS error against Authentik. Inertia::location()
        // must kick in instead: a 409 with X-Inertia-Location, which Inertia's
        // client turns into a real window.location visit.
        //
        // app.asset_url is pinned so the asset-version Inertia's own
        // middleware negotiates is known ahead of time — otherwise a mismatch
        // there makes it override our 409 with its own (pointed back at the
        // same page), which would falsely look like our fix regressed.
        config(['app.asset_url' => 'test-asset-version']);
        $version = hash('xxh128', 'test-asset-version');

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ])->get(route('technical-plan.index'));

        $response->assertStatus(409);
        $location = $response->headers->get('X-Inertia-Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('sso.example.test', $location);
        $this->assertStringContainsString('prompt=none', $location);
    }

    public function test_a_second_visit_to_login_in_the_same_session_renders_normally(): void
    {
        $this->get(route('login'))->assertRedirect();

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }

    public function test_with_sso_unconfigured_login_renders_normally_with_no_redirect(): void
    {
        config(['services.authentik.client_id' => null]);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('ssoEnabled', false));
    }

    public function test_a_failed_silent_login_falls_through_to_the_login_page_with_no_error(): void
    {
        $response = $this->withSession(['sso.silent_attempt' => true])
            ->get(route('auth.authentik.callback', ['error' => 'login_required']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();
        $this->assertGuest();
    }

    public function test_a_failed_interactive_login_falls_through_with_a_visible_error(): void
    {
        $response = $this->withSession(['sso.silent_attempt' => false])
            ->get(route('auth.authentik.callback', ['error' => 'access_denied']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_successful_callback_provisions_a_new_user_with_a_personal_team(): void
    {
        Socialite::fake('authentik', SocialiteUser::fake([
            'id' => 'authentik-subject-1',
            'name' => 'Mari Maasikas',
            'email' => 'mari@example.test',
        ]));

        $this->assertDatabaseMissing('users', ['email' => 'mari@example.test']);

        $response = $this->get(route('auth.authentik.callback'));

        $user = User::where('email', 'mari@example.test')->firstOrFail();

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(SignupSource::AuthentikSso, $user->signup_source);
        $this->assertSame('authentik-subject-1', $user->authentik_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->currentTeam);
        $this->assertTrue($user->currentTeam->is_personal);
    }

    public function test_a_successful_callback_links_an_existing_account_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        Socialite::fake('authentik', SocialiteUser::fake([
            'id' => 'authentik-subject-2',
            'name' => 'Ando',
            'email' => 'ando@ruutu10.ee',
        ]));

        $this->get(route('auth.authentik.callback'));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::count());
        $this->assertSame('authentik-subject-2', $existing->fresh()->authentik_id);
    }

    public function test_a_guest_visiting_the_wizard_entry_is_silently_redirected_and_returns_there(): void
    {
        Socialite::fake('authentik', SocialiteUser::fake([
            'id' => 'authentik-subject-3',
            'name' => 'Jaan',
            'email' => 'jaan@example.test',
        ]));

        $this->get(route('technical-plan.index'))->assertRedirect();

        $response = $this->get(route('auth.authentik.callback'));

        $response->assertRedirect(route('technical-plan.index'));
        $this->assertAuthenticated();
    }

    public function test_logging_out_sets_a_cookie_that_marks_sso_as_just_logged_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect();
        $response->assertCookie('sso_logged_out');
    }

    public function test_the_sso_logged_out_cookie_stops_the_silent_bounce_back(): void
    {
        // The test harness doesn't carry Set-Cookie headers between separate
        // $this->get() calls the way a real browser would, so the cookie a
        // logout response sets is attached explicitly here to simulate that.
        $response = $this->withCookie('sso_logged_out', '1')->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }
}
