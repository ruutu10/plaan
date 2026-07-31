<?php

namespace Tests\Feature;

use App\Actions\MagicLink\LogInAndVerifyEmail;
use App\Enums\SignupSource;
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
}
