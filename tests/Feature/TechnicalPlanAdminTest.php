<?php

namespace Tests\Feature;

use App\Enums\TechnicalPlanStatus;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TechnicalPlanAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user holding the technician role, which carries the view-all permission.
     */
    private function technician(): User
    {
        return User::factory()->create()->assignRole('technician');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('technical-plans.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_the_technician_role_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('technical-plans.index'))
            ->assertForbidden();
    }

    public function test_technicians_can_open_the_overview(): void
    {
        $this->actingAs($this->technician())
            ->get(route('technical-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('technical-plans/Index')
                ->has('plans', 0));
    }

    public function test_the_overview_lists_every_plan_whatever_its_status(): void
    {
        $author = User::factory()->create(['name' => 'Mart Naide', 'email' => 'mart@naide.ee']);
        $team = Team::factory()->create(['name' => 'Märold']);
        $show = Show::factory()->create(['team_id' => $team->id, 'name' => 'Festival 2026']);
        $performance = Performance::factory()->create(['show_id' => $show->id, 'date' => '2026-08-01']);

        $submitted = TechnicalPlan::factory()->submitted()->create([
            'user_id' => $author->id,
            'performance_id' => $performance->id,
        ]);

        // A draft by somebody else, for a performance of another team: the crew
        // sees the whole house, not one team's corner of it. Both are staged
        // before the plan asserted on below, which sorts them after it.
        $draft = TechnicalPlan::factory()->create([
            'status' => TechnicalPlanStatus::Draft,
            'performance_id' => Performance::factory()->create(['date' => '2026-02-01']),
        ]);

        $archived = TechnicalPlan::factory()->create([
            'status' => TechnicalPlanStatus::Archived,
            'performance_id' => Performance::factory()->create(['date' => '2026-01-01']),
        ]);

        $this->actingAs($this->technician())
            ->get(route('technical-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('technical-plans/Index')
                ->has('plans', 3)
                ->where('plans.0.token', $submitted->token)
                ->where('plans.0.showName', 'Festival 2026')
                ->where('plans.0.teamName', 'Märold')
                ->where('plans.0.performanceDate', '2026-08-01')
                ->where('plans.0.submittedBy', 'Mart Naide')
                ->where('plans.0.submittedByEmail', 'mart@naide.ee')
                ->where('plans.0.status', TechnicalPlanStatus::Submitted->value)
                ->where('plans.0.statusLabel', TechnicalPlanStatus::Submitted->label())
                ->where('plans.0.url', route('technical-plan.public', $submitted))
                ->where('plans', fn ($plans) => collect($plans)
                    ->pluck('token')
                    ->contains($draft->token))
                ->where('plans', fn ($plans) => collect($plans)
                    ->pluck('token')
                    ->contains($archived->token)));
    }

    public function test_the_overview_sorts_the_newest_staging_first(): void
    {
        $older = TechnicalPlan::factory()->create([
            'performance_id' => Performance::factory()->create(['date' => '2026-01-10']),
        ]);
        $newer = TechnicalPlan::factory()->create([
            'performance_id' => Performance::factory()->create(['date' => '2026-09-10']),
        ]);

        $this->actingAs($this->technician())
            ->get(route('technical-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('plans.0.token', $newer->token)
                ->where('plans.1.token', $older->token));
    }

    public function test_the_view_all_ability_is_shared_with_the_frontend(): void
    {
        $this->actingAs($this->technician())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.viewAllTechnicalPlans', true));

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.viewAllTechnicalPlans', false));
    }
}
