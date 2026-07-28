<?php

namespace Tests;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
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
