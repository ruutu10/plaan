<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Enums\TeamRole;
use App\Enums\TechnicalPlanStatus;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TechnicalPlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A `startsAt` instant off the page props, read as "H:i" on the venue's
     * clock — the props themselves now carry raw UTC.
     */
    private function venueTime(string $startsAt): string
    {
        return Carbon::parse($startsAt)->setTimezone(Performance::venueTimezone())->format('H:i');
    }

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
        $format = Format::factory()->create(['team_id' => $team->id, 'name' => 'Festival 2026']);

        Performance::factory()->playedAt('Vaba Lava')->create([
            'format_id' => $format->id,
            'date' => now()->addDays(3)->toDateString(),
        ]);
        Performance::factory()->create(['date' => now()->addMonth()->toDateString()]);
        Performance::factory()->past()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcoming.next.formatName', 'Festival 2026')
                ->where('upcoming.next.teamName', 'Märold')
                ->where('upcoming.next.location', 'Vaba Lava')
                ->where('upcoming.next.startsAt', fn ($value) => Carbon::parse($value)
                    ->setTimezone(Performance::venueTimezone())
                    ->toDateString() === now()->addDays(3)->toDateString()));
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
                ->where('upcoming.next.formatName', $performance->format->name));
    }

    public function test_the_plan_timeline_lists_the_newest_submissions_first_for_technicians(): void
    {
        $author = User::factory()->create(['name' => 'Mart Naide']);
        $team = Team::factory()->create(['name' => 'Märold']);
        $format = Format::factory()->create(['team_id' => $team->id, 'name' => 'Festival 2026']);

        $older = TechnicalPlan::factory()->submitted()->create([
            'submitted_at' => now()->subWeek(),
        ]);
        $newer = TechnicalPlan::factory()->submitted()->create([
            'user_id' => $author->id,
            'performance_id' => Performance::factory()->create(['format_id' => $format->id]),
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
                ->where('latestPlans.0.formatName', 'Festival 2026')
                ->where('latestPlans.0.teamName', 'Märold')
                ->where('latestPlans.0.submittedBy', 'Mart Naide')
                ->where('latestPlans.0.url', route('technical-plan.public', $newer))
                ->where('latestPlans.1.token', $older->token));
    }

    public function test_todays_bill_lists_the_performances_by_curtain_up(): void
    {
        $timezone = Performance::venueTimezone();
        $today = Carbon::today($timezone)->toDateString();

        $late = Performance::factory()->create([
            'date' => Performance::momentFrom($today, '21:00'),
        ]);
        $early = Performance::factory()->create([
            'date' => Performance::momentFrom($today, '18:00'),
        ]);

        // Yesterday's and tomorrow's nights are not today's bill.
        Performance::factory()->create([
            'date' => Performance::momentFrom(
                Carbon::today($timezone)->subDay()->toDateString(), '19:00',
            ),
        ]);
        Performance::factory()->create([
            'date' => Performance::momentFrom(
                Carbon::today($timezone)->addDay()->toDateString(), '00:30',
            ),
        ]);

        // The drawer the plans without a night of their own are filed under is
        // not an evening the house is playing.
        Performance::placeholder();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('today', 2)
                ->where('today.0.id', $early->id)
                ->where('today.0.startsAt', fn ($value) => $this->venueTime($value) === '18:00')
                ->where('today.1.id', $late->id)
                ->where('today.1.startsAt', fn ($value) => $this->venueTime($value) === '21:00'));
    }

    public function test_todays_bill_lists_a_performance_nobody_has_handed_a_plan_in_for(): void
    {
        $team = Team::factory()->create(['name' => 'Märold']);
        $format = Format::factory()->create(['team_id' => $team->id, 'name' => 'Festival 2026']);
        $performance = Performance::factory()->playedAt('improkeskus')->create([
            'format_id' => $format->id,
            'date' => $this->tonight(),
        ]);

        // A draft has not been handed in, so the night still reads as unplanned.
        TechnicalPlan::factory()->create([
            'status' => TechnicalPlanStatus::Draft,
            'performance_id' => $performance->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('today', 1)
                ->where('today.0.formatName', 'Festival 2026')
                ->where('today.0.teamName', 'Märold')
                // Where tonight's crew has to be.
                ->where('today.0.location', 'improkeskus')
                ->has('today.0.plans', 0));
    }

    public function test_todays_bill_links_to_the_plans_the_reader_may_open(): void
    {
        $author = User::factory()->create(['name' => 'Mart Naide']);
        $performance = Performance::factory()->create(['date' => $this->tonight()]);

        $plan = TechnicalPlan::factory()->submitted()->create([
            'user_id' => $author->id,
            'performance_id' => $performance->id,
        ]);

        $this->actingAs($author)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('today.0.plans', 1)
                ->where('today.0.plans.0.visible', true)
                ->where('today.0.plans.0.token', $plan->token)
                ->where('today.0.plans.0.url', route('technical-plan.public', $plan))
                ->where('today.0.plans.0.statusLabel', TechnicalPlanStatus::Submitted->label())
                ->where('today.0.plans.0.submittedBy', 'Mart Naide'));
    }

    public function test_todays_bill_names_a_plan_the_reader_may_not_open_without_giving_it_away(): void
    {
        $performance = Performance::factory()->create(['date' => $this->tonight()]);

        TechnicalPlan::factory()->submitted()->create([
            'user_id' => User::factory()->create(['name' => 'Mart Naide'])->id,
            'performance_id' => $performance->id,
        ]);

        // A stranger to the plan's author and to their groups.
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('today.0.plans', 1)
                ->where('today.0.plans.0.visible', false)
                ->where('today.0.plans.0.statusLabel', 'Esitatud, peidetud')
                ->where('today.0.plans.0.token', null)
                ->where('today.0.plans.0.url', null)
                ->where('today.0.plans.0.submittedBy', null));
    }

    public function test_todays_bill_opens_a_team_mates_plan_to_the_whole_group(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->create([
            'format_id' => $format->id,
            'date' => $this->tonight(),
        ]);

        $plan = TechnicalPlan::factory()->submitted()->create([
            'performance_id' => $performance->id,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.0.plans.0.visible', true)
                ->where('today.0.plans.0.token', $plan->token));
    }

    public function test_todays_bill_opens_every_plan_to_the_technical_crew(): void
    {
        $performance = Performance::factory()->create(['date' => $this->tonight()]);

        $first = TechnicalPlan::factory()->submitted()->create([
            'performance_id' => $performance->id,
            'submitted_at' => now()->subHours(2),
        ]);
        $second = TechnicalPlan::factory()->submitted()->create([
            'performance_id' => $performance->id,
            'submitted_at' => now()->subHour(),
        ]);

        $this->actingAs(User::factory()->create()->assignRole('technician'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('today.0.plans', 2)
                ->where('today.0.plans.0.token', $first->token)
                ->where('today.0.plans.0.visible', true)
                ->where('today.0.plans.1.token', $second->token)
                ->where('today.0.plans.1.visible', true));
    }

    public function test_todays_bill_links_the_names_to_the_records_behind_them(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->create([
            'format_id' => $format->id,
            'date' => $this->tonight(),
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.0.formatUrl', route('formats.edit', $format))
                ->where('today.0.performanceUrl', route('formats.performances.show', [$format, $performance])));
    }

    public function test_todays_bill_leaves_a_name_unlinked_for_a_reader_who_may_not_open_it(): void
    {
        // A stranger to the group staging tonight: the night is still on the
        // bill for them, but neither screen behind it would open.
        Performance::factory()->create(['date' => $this->tonight()]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('today', 1)
                ->where('today.0.formatUrl', null)
                ->where('today.0.performanceUrl', null));
    }

    public function test_todays_bill_links_a_guest_troupe_to_the_evening_it_plays_on(): void
    {
        // The format belongs to the house; the guest only has a slot on it. It
        // reaches both screens — the format read-only — to correct its own act.
        $guests = Team::factory()->create();
        $member = User::factory()->create();
        $guests->members()->attach($member, ['role' => TeamRole::Member->value]);

        $format = Format::factory()->create(['team_id' => Team::factory()->create()->id]);
        $performance = Performance::factory()->create([
            'format_id' => $format->id,
            'team_id' => $guests->id,
            'date' => $this->tonight(),
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.0.formatUrl', route('formats.edit', $format))
                ->where('today.0.performanceUrl', route('formats.performances.show', [$format, $performance])));
    }

    public function test_the_next_performance_links_to_the_format_and_the_night(): void
    {
        $format = Format::factory()->create();
        $performance = Performance::factory()->create([
            'format_id' => $format->id,
            'date' => now()->addDays(3),
        ]);

        $this->actingAs(User::factory()->create()->assignRole('technician'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcoming.next.formatUrl', route('formats.edit', $format))
                ->where('upcoming.next.performanceUrl', route('formats.performances.show', [$format, $performance])));
    }

    public function test_the_plan_timeline_links_a_row_to_the_format_that_was_staged(): void
    {
        $format = Format::factory()->create();
        $plan = TechnicalPlan::factory()->submitted()->create([
            'performance_id' => Performance::factory()->create(['format_id' => $format->id]),
            'submitted_at' => now()->subDay(),
        ]);

        // A plan filed under the stand-in night names no evening anybody is
        // playing, so its name leads nowhere.
        TechnicalPlan::factory()->submitted()->create([
            'performance_id' => Performance::placeholder()->id,
            'submitted_at' => now()->subWeek(),
        ]);

        $this->actingAs(User::factory()->create()->assignRole('technician'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('latestPlans', 2)
                ->where('latestPlans.0.token', $plan->token)
                ->where('latestPlans.0.formatUrl', route('formats.edit', $format))
                ->where('latestPlans.1.formatUrl', null));
    }

    /**
     * A curtain-up later today, on the venue's clock.
     */
    private function tonight(): CarbonInterface
    {
        return Performance::momentFrom(
            Carbon::today(Performance::venueTimezone())->toDateString(),
            '19:00',
        );
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

    public function test_my_performances_lists_the_nights_the_reader_is_staffed_on_oldest_first(): void
    {
        $user = User::factory()->create();

        $played = $this->staffing($user, now()->subWeek(), PerformanceStaffRole::Performer);
        $lastNight = $this->staffing($user, now()->subDay(), PerformanceStaffRole::Host);
        $tomorrow = $this->staffing($user, now()->addDay(), PerformanceStaffRole::Technician);
        $nextWeek = $this->staffing($user, now()->addWeek(), PerformanceStaffRole::Bar);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('myPerformances.past', 2)
                ->where('myPerformances.past.0.id', $played->id)
                ->where('myPerformances.past.1.id', $lastNight->id)
                ->has('myPerformances.upcoming', 2)
                ->where('myPerformances.upcoming.0.id', $tomorrow->id)
                ->where('myPerformances.upcoming.1.id', $nextWeek->id));
    }

    public function test_my_performances_reaches_two_nights(): void
    {
        $user = User::factory()->create();

        // Five behind and five ahead: only the two nearest of each side are
        // the reader's business on a dashboard.
        $behind = [];
        $ahead = [];

        for ($nights = 1; $nights <= 5; $nights++) {
            $behind[$nights] = $this->staffing($user, now()->subDays($nights), PerformanceStaffRole::Performer);
            $ahead[$nights] = $this->staffing($user, now()->addDays($nights), PerformanceStaffRole::Performer);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('myPerformances.past', 2)
                // The nearest night behind the reader sits closest to the line,
                // so the past side reads forward through time like the rest.
                ->where('myPerformances.past.0.id', $behind[2]->id)
                ->where('myPerformances.past.1.id', $behind[1]->id)
                ->has('myPerformances.upcoming', 2)
                ->where('myPerformances.upcoming.0.id', $ahead[1]->id)
                ->where('myPerformances.upcoming.1.id', $ahead[2]->id));
    }

    public function test_my_performances_names_every_role_the_reader_holds_on_a_night(): void
    {
        $user = User::factory()->create();

        // Attached compère first, player second, so the row cannot be passing
        // by keeping the order the rows were written in. The pair is also the
        // one where sorting on the enum column itself would read two ways —
        // "performer" before "host" by the enum's own order, after it
        // alphabetically — so the stage comes first whatever the database is.
        $performance = $this->staffing($user, now()->addDay(), PerformanceStaffRole::Host);
        $performance->staff()->attach($user, ['role' => PerformanceStaffRole::Performer->value]);

        // Somebody else's job on the same night is not the reader's, so it is
        // not on their row.
        $performance->staff()->attach(
            User::factory()->create(),
            ['role' => PerformanceStaffRole::Bar->value],
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('myPerformances.upcoming.0.roles', 2)
                ->where('myPerformances.upcoming.0.roles.0.role', PerformanceStaffRole::Performer->value)
                ->where('myPerformances.upcoming.0.roles.0.label', PerformanceStaffRole::Performer->label())
                ->where('myPerformances.upcoming.0.roles.1.role', PerformanceStaffRole::Host->value)
                ->where('myPerformances.upcoming.0.roles.1.label', PerformanceStaffRole::Host->label()));
    }

    public function test_my_performances_leaves_out_a_night_the_reader_has_no_role_on(): void
    {
        $user = User::factory()->create();

        // A night on the house's books that somebody else is staffing, and one
        // nobody is staffing at all.
        $this->staffing(User::factory()->create(), now()->addDay(), PerformanceStaffRole::Performer);
        Performance::factory()->create(['date' => now()->addDays(2)]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('myPerformances.past', 0)
                ->has('myPerformances.upcoming', 0));
    }

    public function test_my_performances_leaves_out_the_stand_in_performance(): void
    {
        $user = User::factory()->create();

        // The drawer the plans without a night of their own are filed in is not
        // an evening anybody is billed for, however it got staffed.
        Performance::placeholder()->staff()->attach(
            $user,
            ['role' => PerformanceStaffRole::Performer->value],
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('myPerformances.past', 0)
                ->has('myPerformances.upcoming', 0));
    }

    public function test_my_performances_links_a_row_to_the_night_it_names(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->create([
            'format_id' => $format->id,
            'date' => now()->addDay(),
        ]);
        $performance->staff()->attach($member, ['role' => PerformanceStaffRole::Performer->value]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'myPerformances.upcoming.0.performanceUrl',
                    route('formats.performances.show', [$format, $performance]),
                )
                ->where('myPerformances.upcoming.0.formatUrl', route('formats.edit', $format)));
    }

    public function test_my_performances_leaves_a_name_unlinked_for_a_reader_who_may_not_open_it(): void
    {
        // A guest performer with a job on a night that belongs to a group they
        // are no part of: the night is theirs to read, not to correct.
        $guest = User::factory()->create();
        $performance = $this->staffing($guest, now()->addDay(), PerformanceStaffRole::Performer);

        $this->actingAs($guest)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('myPerformances.upcoming', 1)
                ->where('myPerformances.upcoming.0.id', $performance->id)
                ->where('myPerformances.upcoming.0.performanceUrl', null)
                ->where('myPerformances.upcoming.0.formatUrl', null));
    }

    public function test_my_performances_carries_what_the_row_names_the_night_by(): void
    {
        $user = User::factory()->create();
        $format = Format::factory()->create(['name' => 'Improkolmapäev']);
        $performance = Performance::factory()->create([
            'format_id' => $format->id,
            'title' => 'Teine pool',
            'location' => 'Kellerteater',
            'date' => now()->addDay(),
        ]);
        $performance->staff()->attach($user, ['role' => PerformanceStaffRole::VideoOperator->value]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('myPerformances.upcoming.0.formatName', 'Improkolmapäev')
                ->where('myPerformances.upcoming.0.title', 'Teine pool')
                ->where('myPerformances.upcoming.0.location', 'Kellerteater')
                ->where('myPerformances.upcoming.0.teamName', $performance->performerName())
                ->where(
                    'myPerformances.upcoming.0.startsAt',
                    $performance->refresh()->date->toIso8601String(),
                ));
    }

    /**
     * A night on the books with the given person holding the given job on it.
     */
    private function staffing(User $user, CarbonInterface $date, PerformanceStaffRole $role): Performance
    {
        $performance = Performance::factory()->create(['date' => $date]);
        $performance->staff()->attach($user, ['role' => $role->value]);

        return $performance;
    }
}
