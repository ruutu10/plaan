<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Enums\TechnicalPlanStatus;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_dashboard_includes_pending_invitations_for_the_authenticated_user()
    {
        $owner = User::factory()->create(['name' => 'Taylor Otwell']);
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create(['name' => 'Laravel Team']);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.code', $invitation->code)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.team.name', 'Laravel Team')
            ->where('pendingInvitations.0.team.slug', $team->slug)
            ->missing('pendingInvitations.0.teamName'),
        );
    }

    public function test_dashboard_does_not_include_accepted_invitations()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        TeamInvitation::factory()->accepted()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 0),
        );
    }

    public function test_dashboard_excludes_expired_invitations_without_deleting_them()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_dashboard_does_not_include_or_delete_other_users_invitations()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'someone@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_dashboard_counts_the_performances_that_are_still_ahead(): void
    {
        Performance::factory()->count(2)->create(['date' => now()->addWeek()]);
        Performance::factory()->create(['date' => now()->addHours(2)]);
        // Curtain-up was an hour ago: this one is being played, not awaited.
        Performance::factory()->create(['date' => now()->subHour()]);
        Performance::factory()->past()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                // Tonight's is still ahead until it starts; one that already
                // started is not, now that a performance carries its hour.
                ->where('upcoming.performances', 3));
    }

    public function test_dashboard_names_the_next_performance_still_to_come(): void
    {
        $team = Team::factory()->create(['name' => 'Märold']);
        $show = Show::factory()->create(['team_id' => $team->id, 'name' => 'Festival 2026']);

        Performance::factory()->create([
            'show_id' => $show->id,
            'date' => now()->addDays(3)->toDateString(),
        ]);
        Performance::factory()->create(['date' => now()->addMonth()->toDateString()]);
        Performance::factory()->past()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcoming.next.showName', 'Festival 2026')
                ->where('upcoming.next.teamName', 'Märold')
                ->where('upcoming.next.date', now()->addDays(3)->toDateString()));
    }

    public function test_dashboard_reports_no_next_performance_when_none_is_ahead(): void
    {
        Performance::factory()->past()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcoming.performances', 0)
                ->where('upcoming.missingPlans', 0)
                ->where('upcoming.next', null));
    }

    public function test_dashboard_counts_the_upcoming_performances_without_a_handed_in_plan(): void
    {
        $covered = Performance::factory()->create(['date' => now()->addWeek()->toDateString()]);
        TechnicalPlan::factory()->submitted()->create(['performance_id' => $covered->id]);

        // A draft is nobody's plan yet, so its performance still counts as missing.
        $drafted = Performance::factory()->create(['date' => now()->addWeek()->toDateString()]);
        TechnicalPlan::factory()->create([
            'status' => TechnicalPlanStatus::Draft,
            'performance_id' => $drafted->id,
        ]);

        Performance::factory()->create(['date' => now()->addWeek()->toDateString()]);

        // Nothing can be done about a performance that has already been played.
        Performance::factory()->past()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcoming.performances', 3)
                ->where('upcoming.missingPlans', 2));
    }

    public function test_dashboard_counts_a_missing_plan_only_once_the_performance_is_near(): void
    {
        // A plan is not expected until the night is a fortnight out, so one
        // booked for the far side of that is owed nothing yet.
        Performance::factory()->create([
            'date' => now()->addDays(TechnicalPlan::EXPECTED_WITHIN_DAYS)->subHour(),
        ]);
        Performance::factory()->create([
            'date' => now()->addDays(TechnicalPlan::EXPECTED_WITHIN_DAYS)->addDay(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcoming.performances', 2)
                ->where('upcoming.missingPlans', 1)
                ->where('upcoming.planExpectedWithinDays', TechnicalPlan::EXPECTED_WITHIN_DAYS));
    }

    public function test_dashboard_does_not_count_the_stand_in_performance(): void
    {
        // The night the plans without a performance of their own are filed
        // under is not an evening the house is playing, so it belongs in
        // neither tally — nor at the top of "what is next", years out as it is.
        $performance = Performance::factory()->create(['date' => now()->addWeek()]);
        Performance::placeholder();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcoming.performances', 1)
                ->where('upcoming.missingPlans', 1)
                ->where('upcoming.next.showName', $performance->show->name));
    }

    public function test_the_plan_timeline_lists_the_newest_submissions_first_for_technicians(): void
    {
        $author = User::factory()->create(['name' => 'Mart Naide']);
        $team = Team::factory()->create(['name' => 'Märold']);
        $show = Show::factory()->create(['team_id' => $team->id, 'name' => 'Festival 2026']);

        $older = TechnicalPlan::factory()->submitted()->create([
            'submitted_at' => now()->subWeek(),
        ]);
        $newer = TechnicalPlan::factory()->submitted()->create([
            'user_id' => $author->id,
            'performance_id' => Performance::factory()->create(['show_id' => $show->id]),
            'submitted_at' => now()->subDay(),
        ]);

        // Drafts have not been handed in, so they are not on the timeline.
        TechnicalPlan::factory()->create(['status' => TechnicalPlanStatus::Draft]);

        $this->actingAs(User::factory()->create()->assignRole('technician'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('latestPlans', 2)
                ->where('latestPlans.0.token', $newer->token)
                ->where('latestPlans.0.showName', 'Festival 2026')
                ->where('latestPlans.0.teamName', 'Märold')
                ->where('latestPlans.0.submittedBy', 'Mart Naide')
                ->where('latestPlans.0.url', route('technical-plan.public', $newer))
                ->where('latestPlans.1.token', $older->token));
    }

    public function test_the_plan_timeline_stays_empty_without_the_view_all_permission(): void
    {
        TechnicalPlan::factory()->submitted()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('latestPlans', 0));
    }
}
