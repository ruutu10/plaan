<?php

namespace Tests;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * A test that reaches the network is a test that is slow, flaky and
     * occasionally billed — and one whose result depends on somebody else's
     * service being up and saying the same thing twice. So every request the
     * HTTP client would make has to be faked by the test that expects it;
     * anything else raises instead of going out.
     *
     * phpunit.xml blanks the credentials that would let a service dial out in
     * the first place, and App\Concerns\CachesClaudeMessages stops the
     * Anthropic SDK, which brings its own HTTP stack and never passes through
     * here.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * A user holding the technician role, which carries the house-wide
     * permissions that see past team boundaries.
     */
    protected function technician(): User
    {
        return User::factory()->create()->assignRole('technician');
    }

    /**
     * Attach the user to a (new) team, owning it unless told otherwise.
     */
    protected function teamOf(User $user, ?string $name = null, TeamRole $role = TeamRole::Owner): Team
    {
        $team = Team::factory()->create($name ? ['name' => $name] : []);

        $team->members()->attach($user, ['role' => $role->value]);

        return $team;
    }
}
