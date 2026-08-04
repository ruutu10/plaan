<?php

namespace Tests\Feature;

use App\Actions\GrantStaffAccess;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use MagicLink\MagicLink;
use Tests\TestCase;

/**
 * An account whose address belongs to the house joins the theatre's team and
 * holds the staff role — but only once that address has been proven, since the
 * role opens every technical plan in the house. Signing in through Authentik is
 * proof in itself; signing up by hand is not, and waits for the verification
 * link to be followed.
 */
class StaffProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.verified_email_domains' => ['ruutu10.ee', 'r10.ee'],
            'teams.theatre_team_name' => 'Ruutu10',
            'services.authentik.base_url' => 'https://sso.example.test',
            'services.authentik.client_id' => 'test-client-id',
            'services.authentik.client_secret' => 'test-client-secret',
            'services.authentik.redirect' => 'http://localhost/auth/authentik/callback',
        ]);
    }

    public function test_registering_with_a_house_address_grants_nothing_until_the_address_is_verified(): void
    {
        Notification::fake();

        $theatre = Team::factory()->create(['name' => 'Ruutu10']);

        $this->post(route('register.store'), [
            'name' => 'Mari Maasikas',
            'email' => 'mari@ruutu10.ee',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'mari@ruutu10.ee')->firstOrFail();

        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertFalse($user->hasRole(GrantStaffAccess::ROLE));
        $this->assertFalse($user->can(TechnicalPlan::VIEW_ALL_PERMISSION));
        $this->assertNull($user->teamRole($theatre));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_following_the_verification_link_takes_a_house_address_into_the_theatre_team(): void
    {
        $theatre = Team::factory()->create(['name' => 'Ruutu10']);

        $this->post(route('register.store'), [
            'name' => 'Mari Maasikas',
            'email' => 'mari@ruutu10.ee',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'mari@ruutu10.ee')->firstOrFail();

        $this->actingAs($user)->get($this->verificationUrl($user));

        $user = $user->fresh();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole(GrantStaffAccess::ROLE));
        $this->assertTrue($user->can(TechnicalPlan::VIEW_ALL_PERMISSION));
        $this->assertSame(TeamRole::Member, $user->teamRole($theatre));

        // A fresh registration has no team of its own, so the house team is
        // where they land.
        $this->assertTrue($user->currentTeam->is($theatre));
    }

    public function test_verifying_an_address_from_another_domain_grants_nothing(): void
    {
        $theatre = Team::factory()->create(['name' => 'Ruutu10']);

        $this->post(route('register.store'), [
            'name' => 'Väline Kasutaja',
            'email' => 'keegi@naide.ee',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'keegi@naide.ee')->firstOrFail();

        $this->actingAs($user)->get($this->verificationUrl($user));

        $user = $user->fresh();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->hasRole(GrantStaffAccess::ROLE));
        $this->assertFalse($user->can(TechnicalPlan::VIEW_ALL_PERMISSION));
        $this->assertNull($user->teamRole($theatre));
    }

    public function test_an_sso_login_with_a_house_address_provisions_a_staff_account_at_once(): void
    {
        $theatre = Team::factory()->create(['name' => 'Ruutu10']);

        Socialite::fake('authentik', SocialiteUser::fake([
            'id' => 'authentik-subject-staff',
            'name' => 'Ando',
            'email' => 'ando@r10.ee',
        ]));

        $this->get(route('auth.authentik.callback'));

        $user = User::where('email', 'ando@r10.ee')->firstOrFail();

        // Authentik is the authority on the address, so there is nothing left
        // to prove and no verification mail to wait for.
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole(GrantStaffAccess::ROLE));
        $this->assertSame(TeamRole::Member, $user->teamRole($theatre));
        $this->assertTrue($user->currentTeam->is($theatre));
    }

    public function test_an_sso_login_from_another_domain_provisions_an_ordinary_account(): void
    {
        Socialite::fake('authentik', SocialiteUser::fake([
            'id' => 'authentik-subject-outsider',
            'name' => 'Mari Maasikas',
            'email' => 'mari@example.test',
        ]));

        $this->get(route('auth.authentik.callback'));

        $user = User::where('email', 'mari@example.test')->firstOrFail();

        $this->assertFalse($user->hasRole(GrantStaffAccess::ROLE));
        $this->assertSame(0, Team::where('name', 'Ruutu10')->count());
        $this->assertNull($user->fresh()->current_team_id);
    }

    public function test_a_magic_link_account_with_a_house_address_becomes_staff_once_the_link_is_followed(): void
    {
        Notification::fake();

        $theatre = Team::factory()->create(['name' => 'Ruutu10']);

        $this->postJson(route('technical-plan.login'), [
            'email' => 'Uus@Ruutu10.ee',
        ])->assertOk();

        $user = User::where('email', 'uus@ruutu10.ee')->firstOrFail();

        // Asking for the link proves nothing — anybody can type the address.
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertFalse($user->hasRole(GrantStaffAccess::ROLE));
        $this->assertNull($user->teamRole($theatre));

        $this->get($this->mailedMagicLink());

        $user = $user->fresh();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole(GrantStaffAccess::ROLE));
        $this->assertSame(TeamRole::Member, $user->teamRole($theatre));

        // These accounts get no team of their own, so the house team is the
        // only place they can start from.
        $this->assertTrue($user->currentTeam->is($theatre));
    }

    public function test_following_a_magic_link_from_another_domain_verifies_but_grants_nothing(): void
    {
        Notification::fake();

        $this->postJson(route('technical-plan.login'), [
            'email' => 'uus@naide.ee',
        ])->assertOk();

        $user = User::where('email', 'uus@naide.ee')->firstOrFail();

        $this->get($this->mailedMagicLink());

        $user = $user->fresh();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->hasRole(GrantStaffAccess::ROLE));
        $this->assertNull($user->current_team_id);
    }

    public function test_an_unverified_house_address_is_refused_outright(): void
    {
        Team::factory()->create(['name' => 'Ruutu10']);

        $user = User::factory()->unverified()->create(['email' => 'ando@ruutu10.ee']);

        $this->assertFalse(app(GrantStaffAccess::class)->handle($user));
        $this->assertFalse($user->fresh()->hasRole(GrantStaffAccess::ROLE));
    }

    public function test_an_existing_account_is_left_as_it_is_by_a_magic_link(): void
    {
        Notification::fake();

        $theatre = Team::factory()->create(['name' => 'Ruutu10']);
        $user = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        $this->postJson(route('technical-plan.login'), [
            'email' => 'ando@ruutu10.ee',
        ])->assertOk();

        $this->assertFalse($user->fresh()->hasRole(GrantStaffAccess::ROLE));
        $this->assertNull($user->fresh()->teamRole($theatre));
    }

    public function test_the_theatre_team_is_created_when_it_is_missing(): void
    {
        $user = User::factory()->create(['email' => 'mari@ruutu10.ee']);

        app(GrantStaffAccess::class)->handle($user);

        $theatre = Team::where('name', 'Ruutu10')->firstOrFail();

        $this->assertSame('ruutu10', $theatre->slug);
        $this->assertSame(TeamRole::Member, $user->fresh()->teamRole($theatre));
    }

    public function test_granting_twice_leaves_a_single_membership_and_a_single_role(): void
    {
        $theatre = Team::factory()->create(['name' => 'Ruutu10']);
        $user = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        $grant = app(GrantStaffAccess::class);

        $this->assertTrue($grant->handle($user));
        $this->assertTrue($grant->handle($user->fresh()));

        $this->assertSame(1, $user->teamMemberships()->where('team_id', $theatre->id)->count());
        $this->assertSame(1, $user->fresh()->roles()->count());
    }

    public function test_an_unverified_account_may_not_reach_the_pages_behind_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('formats.index'))
            ->assertRedirect(route('verification.notice'));
    }

    /**
     * The one-time link the login endpoint has just sent out, read back from
     * where it was stored so a test can follow it as the recipient would.
     */
    private function mailedMagicLink(): string
    {
        return MagicLink::query()->firstOrFail()->url;
    }

    /**
     * The signed link Laravel mails out, built here so a test can follow it.
     */
    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );
    }
}
