<?php

namespace Tests\Feature;

use App\Actions\MagicLink\LogInAndVerifyEmail;
use App\Enums\SignupSource;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use MagicLink\MagicLink;
use Tests\TestCase;

class MagicLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_emails_a_magic_link_to_an_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        $response = $this->postJson(route('technical-plan.login'), [
            'email' => 'ando@ruutu10.ee',
        ]);

        $response->assertOk();
        $response->assertJson(['sent' => true]);

        $this->assertSame(1, User::count());
        Notification::assertSentTo($user, MagicLoginLink::class);
    }

    public function test_it_provisions_a_new_user_before_sending_the_link(): void
    {
        Notification::fake();

        $this->assertDatabaseMissing('users', ['email' => 'uus@naide.ee']);

        $this->postJson(route('technical-plan.login'), [
            'email' => 'uus@naide.ee',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'uus@naide.ee',
            'signup_source' => SignupSource::AnonymousPlan->value,
        ]);

        $user = User::where('email', 'uus@naide.ee')->firstOrFail();
        Notification::assertSentTo($user, MagicLoginLink::class);
    }

    public function test_the_email_is_normalised_and_validated(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        // Mixed-case input resolves to the same lower-cased account.
        $this->postJson(route('technical-plan.login'), [
            'email' => 'Ando@Ruutu10.EE',
        ])->assertOk();

        $this->assertSame(1, User::count());
        Notification::assertSentTo($user, MagicLoginLink::class);

        $this->postJson(route('technical-plan.login'), [
            'email' => 'not-an-email',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_the_email_renders_with_ruutu10_branding(): void
    {
        // No Notification::fake() here: let the mail actually render through the
        // array transport so a broken Blade view or logo embed would surface.
        $user = User::factory()->create(['name' => 'Mari']);

        $user->notify(new MagicLoginLink('https://example.test/magiclink/tok123'));

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $html = $email->getHtmlBody();

        $this->assertStringContainsString('https://example.test/magiclink/tok123', $html);
        $this->assertStringContainsString('Logi sisse', $html);
        $this->assertStringContainsString('Mari', $html);
        $this->assertStringContainsString('#11234f', $html); // navy brand colour
        $this->assertStringContainsString('#ff7f50', $html); // orange brand colour
        // The logo is embedded inline (CID), not linked to an external host.
        $this->assertStringContainsString('cid:', $html);
        $this->assertNotEmpty($email->getAttachments());
    }

    public function test_visiting_the_magic_link_logs_the_user_in(): void
    {
        $user = User::factory()->create();

        $url = MagicLink::create(
            new LogInAndVerifyEmail($user, redirect()->route('technical-plan.index')),
        )->url;

        $response = $this->get($url);

        $response->assertRedirect(route('technical-plan.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_link_asked_for_from_a_shared_plan_returns_to_that_plan(): void
    {
        Notification::fake();

        // Somebody reading a plan by its share link, logging in to work on it:
        // the wizard sends the plan's key along with the address.
        $plan = TechnicalPlan::factory()->submitted()->create();
        $user = User::factory()->create(['email' => 'lugeja@naide.ee']);

        $this->postJson(route('technical-plan.login'), [
            'email' => 'lugeja@naide.ee',
            'token' => $plan->token,
        ])->assertOk();

        $response = $this->get($this->mailedLink($user));

        $response->assertRedirect(route('technical-plan.public', $plan));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_link_asked_for_from_a_plan_that_is_gone_still_logs_the_user_in(): void
    {
        Notification::fake();

        // A key that names nothing is a plan deleted since the link was shared.
        // Landing on the wizard beats being unable to log in at all.
        $user = User::factory()->create(['email' => 'lugeja@naide.ee']);

        $this->postJson(route('technical-plan.login'), [
            'email' => 'lugeja@naide.ee',
            'token' => 'R10-2026-KADUNUD1234',
        ])->assertOk();

        $response = $this->get($this->mailedLink($user));

        $response->assertRedirect(route('technical-plan.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_page_mails_a_link_and_confirms_it_on_screen(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        $response = $this->from(route('login'))->post(route('login.magic-link'), [
            'email' => 'ando@ruutu10.ee',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'ando@ruutu10.ee'));

        Notification::assertSentTo($user, MagicLoginLink::class);
    }

    public function test_a_link_asked_for_on_the_login_page_lands_on_the_dashboard(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        $this->post(route('login.magic-link'), ['email' => 'ando@ruutu10.ee']);

        $response = $this->get($this->mailedLink($user));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_link_asked_for_on_the_login_page_returns_to_the_page_that_wanted_a_login(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        // The guard puts the wanted page in the session on its way to /login;
        // the mailed link should honour it rather than the dashboard.
        $this->get(route('formats.index'))->assertRedirect(route('login'));

        $this->post(route('login.magic-link'), ['email' => 'ando@ruutu10.ee']);

        $response = $this->get($this->mailedLink($user));

        $response->assertRedirect(route('formats.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_page_registers_an_unknown_address_as_a_signup(): void
    {
        Notification::fake();

        $this->post(route('login.magic-link'), ['email' => 'uus@naide.ee'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'uus@naide.ee',
            'signup_source' => SignupSource::SignupForm->value,
        ]);
    }

    public function test_the_login_page_rejects_an_invalid_address(): void
    {
        Notification::fake();

        $this->from(route('login'))
            ->post(route('login.magic-link'), ['email' => 'not-an-email'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_an_already_signed_in_user_has_no_use_for_a_login_link(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('login.magic-link'), ['email' => 'ando@ruutu10.ee'])
            ->assertRedirect(route('dashboard'));

        Notification::assertNothingSent();
    }

    public function test_visiting_the_magic_link_settles_an_unverified_address(): void
    {
        Notification::fake();

        $this->postJson(route('technical-plan.login'), [
            'email' => 'uus@naide.ee',
        ])->assertOk();

        $user = User::where('email', 'uus@naide.ee')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());

        // Reading the mailbox the link was sent to is the same proof the
        // verification mail asks for, so no second link is needed.
        $this->get(MagicLink::query()->firstOrFail()->url);

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_an_already_verified_address_is_left_alone_by_a_magic_link(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at;

        $url = MagicLink::create(
            new LogInAndVerifyEmail($user, redirect()->route('technical-plan.index')),
        )->url;

        $this->get($url);

        Event::assertNotDispatched(Verified::class);
        $this->assertTrue($user->fresh()->email_verified_at->equalTo($verifiedAt));
    }

    /**
     * The one-time URL the user was just sent, taken off the notification.
     */
    private function mailedLink(User $user): string
    {
        $url = '';

        Notification::assertSentTo($user, MagicLoginLink::class, function (MagicLoginLink $notification) use (&$url): bool {
            $url = $notification->url;

            return true;
        });

        return $url;
    }
}
