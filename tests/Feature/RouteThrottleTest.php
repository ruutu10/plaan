<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The limits set on single routes in routes/web.php, each kept on a counter of
 * its own.
 *
 * An unprefixed `throttle` keys on the user (or, for a guest, the address) and
 * nothing else, so every such limit in the app shares one count: a route's own
 * allowance would be spent by whatever else the user did that minute, the
 * API groups' `throttle:200,1` included. Every per-route throttle therefore
 * carries a prefix; these tests fail as soon as one of them loses it.
 */
class RouteThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_traffic_through_a_groups_throttle_does_not_spend_a_routes_own_allowance(): void
    {
        $user = User::factory()->create();

        // Past the attachments' 20 a minute, all of it through the plan API's
        // own 200 a minute.
        for ($request = 0; $request < 25; $request++) {
            $this->actingAs($user)->getJson(route('technical-plan.performances'))->assertOk();
        }

        $this->actingAs($user)
            ->deleteJson(route('attachments.destroy', Str::uuid()->toString()))
            ->assertOk();
    }

    public function test_two_routes_limits_are_counted_apart(): void
    {
        $user = User::factory()->create();

        for ($request = 0; $request < 20; $request++) {
            $this->actingAs($user)->deleteJson(route('attachments.destroy', Str::uuid()->toString()))->assertOk();
        }

        $this->actingAs($user)
            ->deleteJson(route('attachments.destroy', Str::uuid()->toString()))
            ->assertTooManyRequests();

        // Discarding is spent; uploading is not. Refused for the empty body,
        // not for the count.
        $this->actingAs($user)
            ->postJson(route('attachments.store'))
            ->assertUnprocessable();
    }

    public function test_both_doors_that_mail_a_magic_link_share_one_allowance(): void
    {
        // Each asks for the same mail to be sent, so six from one address is
        // six whichever page they came through. An empty address is sent back by
        // validation, which the throttle has already counted by then.
        for ($request = 0; $request < 6; $request++) {
            $this->post(route('login.magic-link'), ['email' => ''])->assertSessionHasErrors('email');
        }

        $this->postJson(route('technical-plan.login'), ['email' => ''])->assertTooManyRequests();
    }
}
