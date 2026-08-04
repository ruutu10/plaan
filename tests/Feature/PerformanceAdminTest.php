<?php

namespace Tests\Feature;

use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The crew's overview of every performance in the house — who reaches it, and
 * what a row says once they do.
 */
class PerformanceAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.performances.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_the_permission_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.performances.index'))
            ->assertForbidden();
    }

    /**
     * Belonging to a group that stages plenty is not the same right: the
     * overview is the whole house's, so it takes the house-wide permission.
     */
    public function test_a_member_of_a_team_is_still_forbidden(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        Performance::factory()->create([
            'format_id' => Format::factory()->create(['team_id' => $team->id]),
        ]);

        $this->actingAs($user)
            ->get(route('admin.performances.index'))
            ->assertForbidden();
    }

    public function test_technicians_can_open_the_overview(): void
    {
        $this->actingAs($this->technician())
            ->get(route('admin.performances.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/performances/Index')
                ->has('performances', 0));
    }

    public function test_the_overview_lists_every_performance_of_every_team(): void
    {
        $ours = Format::factory()->create([
            'team_id' => Team::factory()->create(['name' => 'Märold']),
            'name' => 'Festival 2026',
        ]);
        $theirs = Format::factory()->create([
            'team_id' => Team::factory()->create(['name' => 'Teine tiim']),
        ]);

        $performance = Performance::factory()
            ->startingAt('2026-09-01', '19:30')
            ->create(['format_id' => $ours->id, 'duration' => 75]);

        TechnicalPlan::factory()->submitted()->create(['performance_id' => $performance->id]);

        // Another group's night, dated before the one asserted on so the order
        // of the two rows is the sorting's doing rather than the factory's.
        Performance::factory()->startingAt('2026-08-01')->create(['format_id' => $theirs->id]);

        $this->actingAs($this->technician())
            ->get(route('admin.performances.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('performances', 2)
                ->has('performances', fn (Assert $rows) => $rows
                    ->where('0.id', $performance->id)
                    ->where('0.formatId', $ours->id)
                    ->where('0.formatName', 'Festival 2026')
                    ->where('0.teamName', 'Märold')
                    ->where('0.date', '2026-09-01')
                    ->where('0.startTime', '19:30')
                    ->where('0.duration', 75)
                    ->where('0.isDraft', false)
                    ->where('0.technicalPlanCount', 1)
                    ->where('0.title', null)
                    ->etc()));
    }

    /**
     * An act on an evening several groups share is announced under its own
     * group and its own name, not the format owner's — the same rule the rest of
     * the app follows.
     */
    public function test_an_act_on_a_shared_evening_carries_its_own_team_and_title(): void
    {
        $format = Format::factory()->create([
            'team_id' => Team::factory()->create(['name' => 'Korraldaja']),
        ]);
        $guest = Team::factory()->create(['name' => 'Külaline']);

        Performance::factory()
            ->performedBy($guest, 'Improkava')
            ->create(['format_id' => $format->id]);

        $this->actingAs($this->technician())
            ->get(route('admin.performances.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('performances.0.teamName', 'Külaline')
                ->where('performances.0.title', 'Improkava'));
    }

    public function test_the_overview_shows_performances_still_waiting_to_be_reviewed(): void
    {
        Performance::factory()->draft()->create();

        $this->actingAs($this->technician())
            ->get(route('admin.performances.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('performances', 1)
                ->where('performances.0.isDraft', true));
    }

    public function test_the_overview_sorts_the_newest_performance_first(): void
    {
        $older = Performance::factory()->startingAt('2026-08-01')->create();
        $newer = Performance::factory()->startingAt('2026-09-10')->create();

        $this->actingAs($this->technician())
            ->get(route('admin.performances.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('performances.0.id', $newer->id)
                ->where('performances.1.id', $older->id));
    }

    public function test_the_manage_all_ability_is_shared_with_the_frontend(): void
    {
        $this->actingAs($this->technician())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageAllPerformances', true));

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageAllPerformances', false));
    }
}
