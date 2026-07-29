<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_refused(): void
    {
        $show = Show::factory()->create();

        $this->getJson(route('api.shows.performances.index', $show))
            ->assertUnauthorized();
    }

    public function test_a_member_can_list_the_performances_of_their_teams_show(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        $later = Performance::factory()->create(['show_id' => $show->id, 'date' => '2026-09-01']);
        $sooner = Performance::factory()->create(['show_id' => $show->id, 'date' => '2026-08-01', 'duration' => 75]);

        $this->actingAs($user)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Soonest first.
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.0.date', '2026-08-01')
            ->assertJsonPath('data.0.duration', 75)
            ->assertJsonPath('data.0.technicalPlanCount', 0)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_a_show_without_performances_lists_none(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        $this->actingAs($user)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_performances_of_another_teams_show_are_forbidden(): void
    {
        $show = Show::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.shows.performances.index', $show))
            ->assertForbidden();
    }

    public function test_a_member_can_add_a_performance(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.shows.performances.store', $show), [
                'date' => '2026-08-14',
                'duration' => 90,
            ])
            ->assertCreated()
            ->assertJsonPath('data.date', '2026-08-14')
            ->assertJsonPath('data.duration', 90)
            ->assertJsonPath('data.technicalPlanCount', 0);

        $performance = Performance::sole();

        $this->assertSame($show->id, $performance->show_id);
        $this->assertSame('2026-08-14', $performance->date->toDateString());
        $this->assertSame(90, $performance->duration);
    }

    public function test_a_performance_may_be_added_without_a_duration(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.shows.performances.store', $show), ['date' => '2026-08-14'])
            ->assertCreated()
            ->assertJsonPath('data.duration', null);
    }

    public function test_a_blank_duration_counts_as_no_duration(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.shows.performances.store', $show), [
                'date' => '2026-08-14',
                'duration' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('data.duration', null);
    }

    public function test_adding_a_performance_to_another_teams_show_is_forbidden(): void
    {
        $show = Show::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.shows.performances.store', $show), ['date' => '2026-08-14'])
            ->assertForbidden();

        $this->assertDatabaseCount('performances', 0);
    }

    public function test_a_performance_needs_a_valid_date_and_a_sane_duration(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.shows.performances.store', $show), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');

        $this->actingAs($user)
            ->postJson(route('api.shows.performances.store', $show), [
                'date' => '14.08.2026',
                'duration' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'duration']);
    }

    public function test_a_member_can_update_a_performance(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $performance = Performance::factory()->create(['show_id' => $show->id, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->patchJson(route('api.shows.performances.update', [$show, $performance]), [
                'date' => '2026-08-02',
                'duration' => 120,
            ])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-08-02')
            ->assertJsonPath('data.duration', 120);

        $performance->refresh();

        $this->assertSame('2026-08-02', $performance->date->toDateString());
        $this->assertSame(120, $performance->duration);
    }

    public function test_the_listing_says_which_performances_wait_to_be_reviewed(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        Performance::factory()->draft()->create(['show_id' => $show->id, 'date' => '2026-08-01']);
        Performance::factory()->create(['show_id' => $show->id, 'date' => '2026-08-02']);

        $this->actingAs($user)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonPath('data.0.isDraft', true)
            ->assertJsonPath('data.1.isDraft', false);
    }

    public function test_a_performance_added_by_hand_is_not_a_draft(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        // Adding it here is the review, so there is nothing left to vouch for.
        $this->actingAs($user)
            ->postJson(route('api.shows.performances.store', $show), ['date' => '2026-08-14'])
            ->assertCreated()
            ->assertJsonPath('data.isDraft', false);

        $this->assertFalse(Performance::sole()->is_draft);
    }

    public function test_a_member_can_clear_a_performance_that_waited_to_be_reviewed(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $performance = Performance::factory()->draft()->create(['show_id' => $show->id, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->patchJson(route('api.shows.performances.update', [$show, $performance]), [
                'date' => '2026-08-01',
                'is_draft' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.isDraft', false);

        $this->assertFalse($performance->fresh()->is_draft);
    }

    public function test_a_member_can_put_a_performance_back_to_waiting(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $performance = Performance::factory()->create(['show_id' => $show->id, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->patchJson(route('api.shows.performances.update', [$show, $performance]), [
                'date' => '2026-08-01',
                'is_draft' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.isDraft', true);

        $this->assertTrue($performance->fresh()->is_draft);
    }

    public function test_a_saved_performance_keeps_its_standing_when_the_field_is_left_out(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $performance = Performance::factory()->draft()->create(['show_id' => $show->id, 'date' => '2026-08-01']);

        // A client that does not offer the toggle must not clear the flag by
        // saving the date alone.
        $this->actingAs($user)
            ->patchJson(route('api.shows.performances.update', [$show, $performance]), [
                'date' => '2026-08-02',
            ])
            ->assertOk()
            ->assertJsonPath('data.isDraft', true);

        $this->assertTrue($performance->fresh()->is_draft);
    }

    public function test_the_draft_flag_must_be_a_boolean(): void
    {
        [$user, $show] = $this->showOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.shows.performances.store', $show), [
                'date' => '2026-08-14',
                'is_draft' => 'vahest',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_draft');
    }

    public function test_updating_another_teams_performance_is_forbidden(): void
    {
        $performance = Performance::factory()->create(['date' => '2026-08-01']);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('api.shows.performances.update', [$performance->show, $performance]), [
                'date' => '2026-09-09',
            ])
            ->assertForbidden();

        $this->assertSame('2026-08-01', $performance->fresh()->date->toDateString());
    }

    public function test_a_member_can_delete_a_performance(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $performance = Performance::factory()->create(['show_id' => $show->id]);

        $this->actingAs($user)
            ->deleteJson(route('api.shows.performances.destroy', [$show, $performance]))
            ->assertNoContent();

        // Put aside, not destroyed — and gone from the listing either way.
        $this->assertSoftDeleted($performance);

        $this->actingAs($user)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_deleted_performance_keeps_the_technical_plans_written_for_it(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $performance = Performance::factory()->create(['show_id' => $show->id]);
        $plan = TechnicalPlan::factory()->create(['performance_id' => $performance->id]);

        $this->actingAs($user)
            ->deleteJson(route('api.shows.performances.destroy', [$show, $performance]))
            ->assertNoContent();

        // The plan survives, and so does its trail back to the performance: the
        // performance is only hidden, so restoring it would join the two up again.
        $this->assertDatabaseHas('technical_plans', [
            'id' => $plan->id,
            'performance_id' => $performance->id,
        ]);

        // Until then the plan reads as one without a performance, because the
        // relation does not reach through a soft delete.
        $this->assertNull($plan->fresh()->performance);
    }

    public function test_a_deleted_performance_can_no_longer_be_reached(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $performance = Performance::factory()->trashed()->create(['show_id' => $show->id]);

        $this->actingAs($user)
            ->patchJson(route('api.shows.performances.update', [$show, $performance]), [
                'date' => '2026-09-09',
            ])
            ->assertNotFound();
    }

    public function test_the_number_of_plans_is_listed_so_the_screen_can_warn(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $performance = Performance::factory()->create(['show_id' => $show->id]);
        TechnicalPlan::factory()->count(2)->create(['performance_id' => $performance->id]);

        $this->actingAs($user)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonPath('data.0.technicalPlanCount', 2);
    }

    public function test_deleting_another_teams_performance_is_forbidden(): void
    {
        $performance = Performance::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('api.shows.performances.destroy', [$performance->show, $performance]))
            ->assertForbidden();

        $this->assertDatabaseHas('performances', ['id' => $performance->id]);
    }

    public function test_a_performance_cannot_be_reached_through_another_shows_url(): void
    {
        [$user, $show] = $this->showOfOwnTeam();
        $strangersPerformance = Performance::factory()->create();

        // The user may manage $show's performances, but this one is not among them.
        $this->actingAs($user)
            ->patchJson(route('api.shows.performances.update', [$show, $strangersPerformance]), [
                'date' => '2026-09-09',
            ])
            ->assertNotFound();
    }

    public function test_a_technician_may_manage_the_performances_of_any_show(): void
    {
        $technician = User::factory()->create()->assignRole('technician');
        $show = Show::factory()->create();
        $performance = Performance::factory()->create(['show_id' => $show->id]);

        $this->actingAs($technician)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($technician)
            ->postJson(route('api.shows.performances.store', $show), ['date' => '2026-12-01'])
            ->assertCreated();

        $this->actingAs($technician)
            ->patchJson(route('api.shows.performances.update', [$show, $performance]), [
                'date' => '2026-12-02',
            ])
            ->assertOk();

        $this->actingAs($technician)
            ->deleteJson(route('api.shows.performances.destroy', [$show, $performance]))
            ->assertNoContent();
    }

    public function test_the_edit_all_permission_stands_on_its_own(): void
    {
        $user = User::factory()->create()->givePermissionTo(Performance::EDIT_ALL_PERMISSION);
        $show = Show::factory()->create();

        // The performances of anybody's show: yes.
        $this->actingAs($user)
            ->postJson(route('api.shows.performances.store', $show), ['date' => '2026-12-01'])
            ->assertCreated();

        // The show itself: no — that is what shows.edit_all is for.
        $this->actingAs($user)
            ->getJson(route('api.shows.show', $show))
            ->assertForbidden();
    }

    /**
     * A user with a team of their own, and a show that team owns.
     *
     * @return array{0: User, 1: Show}
     */
    private function showOfOwnTeam(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        return [$user, Show::factory()->create(['team_id' => $team->id])];
    }
}
