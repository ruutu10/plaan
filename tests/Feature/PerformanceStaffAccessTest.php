<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Models\Format;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What being on a performance's staff — read off the card or added by hand —
 * opens: the night's details screen to read, and its technical plans to read
 * and write. Changing the night itself stays with its groups and the crew.
 */
class PerformanceStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_staff_may_open_the_details_page(): void
    {
        [$performance, $worker] = $this->staffedNight(manually: false);

        $this->actingAs($worker)
            ->get(route('formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('formats/performances/Show'));
    }

    public function test_manually_added_staff_may_open_the_details_page(): void
    {
        [$performance, $worker] = $this->staffedNight(manually: true);

        $this->actingAs($worker)
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertJsonPath('data.id', $performance->id);
    }

    public function test_staff_read_the_details_without_the_editors_data(): void
    {
        [$performance, $worker] = $this->staffedNight();

        $this->actingAs($worker)
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertJsonPath('can.edit', false)
            ->assertJsonPath('can.openFormat', false)
            ->assertJsonPath('teams', [])
            ->assertJsonPath('reminderRecipients', [])
            ->assertJsonPath('staffRoles', [])
            ->assertJsonCount(1, 'data.staff');
    }

    public function test_editors_are_told_they_may_edit(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $performance = Performance::factory()->for(Format::factory()->create(['team_id' => $team->id]))->create();

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertJsonPath('can.edit', true)
            ->assertJsonPath('can.openFormat', true);
    }

    public function test_staff_may_not_change_delete_or_restaff_the_night(): void
    {
        [$performance, $worker] = $this->staffedNight();

        $this->actingAs($worker)
            ->patchJson(route('api.formats.performances.update', [$performance->format, $performance]), [])
            ->assertForbidden();

        $this->actingAs($worker)
            ->deleteJson(route('api.formats.performances.destroy', [$performance->format, $performance]))
            ->assertForbidden();

        $this->actingAs($worker)
            ->postJson(route('api.formats.performances.staff.store', [$performance->format, $performance]), [
                'user_id' => User::factory()->create()->id,
                'role' => PerformanceStaffRole::Bar->value,
            ])
            ->assertForbidden();

        $this->assertModelExists($performance);
    }

    public function test_working_one_night_opens_no_other(): void
    {
        [, $worker] = $this->staffedNight();
        $elsewhere = Performance::factory()->create();

        $this->actingAs($worker)
            ->getJson(route('api.formats.performances.show', [$elsewhere->format, $elsewhere]))
            ->assertForbidden();
    }

    public function test_staff_see_the_nights_plans_in_the_overview(): void
    {
        [$performance, $worker] = $this->staffedNight();
        $plan = TechnicalPlan::factory()->submitted()->create(['performance_id' => $performance->id]);
        TechnicalPlan::factory()->submitted()->create();

        $this->actingAs($worker)
            ->get(route('technical-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 1)
                ->where('plans.0.token', $plan->token));
    }

    public function test_staff_may_open_the_nights_plan(): void
    {
        [$performance, $worker] = $this->staffedNight(manually: true);
        $plan = TechnicalPlan::factory()->submitted()->create(['performance_id' => $performance->id]);

        $this->actingAs($worker)
            ->get(route('technical-plans.show', $plan))
            ->assertOk();
    }

    public function test_staff_in_any_role_may_edit_the_nights_plans_drafts_included(): void
    {
        [$performance, $worker] = $this->staffedNight(role: PerformanceStaffRole::Bar);
        $draft = TechnicalPlan::factory()->create(['performance_id' => $performance->id]);
        $sent = TechnicalPlan::factory()->submitted()->create(['performance_id' => $performance->id]);

        $this->assertTrue($draft->isEditableBy($worker));
        $this->assertTrue($sent->isEditableBy($worker));
    }

    public function test_staff_may_reuse_the_nights_sent_plans_but_not_its_drafts(): void
    {
        [$performance, $worker] = $this->staffedNight();
        $draft = TechnicalPlan::factory()->create(['performance_id' => $performance->id]);
        $sent = TechnicalPlan::factory()->submitted()->create(['performance_id' => $performance->id]);

        $this->assertTrue($sent->isVisibleTo($worker));
        $this->assertFalse($draft->isVisibleTo($worker));
    }

    public function test_staff_get_no_reach_into_other_nights_plans(): void
    {
        [, $worker] = $this->staffedNight();
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->assertFalse($plan->isEditableBy($worker));
        $this->assertFalse($plan->isListableBy($worker));
        $this->assertFalse($plan->isVisibleTo($worker));
    }

    public function test_the_dashboard_links_staff_to_the_nights_details_page(): void
    {
        [$performance, $worker] = $this->staffedNight();
        $performance->update(['date' => now()->addDays(3)]);

        $this->actingAs($worker)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('myPerformances.upcoming.0.performanceUrl', route('formats.performances.show', [$performance->format_id, $performance->id])));
    }

    /**
     * A performance of some other group's format, and somebody who works it
     * and belongs to none of its groups.
     *
     * @return array{Performance, User}
     */
    private function staffedNight(PerformanceStaffRole $role = PerformanceStaffRole::Technician, bool $manually = false): array
    {
        $performance = Performance::factory()->create();
        $worker = User::factory()->create();

        $performance->staffings()->create([
            'user_id' => $worker->id,
            'role' => $role,
            'manually_added' => $manually,
        ]);

        return [$performance, $worker];
    }
}
