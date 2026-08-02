<?php

namespace Tests\Feature;

use App\Enums\TechnicalPlanStatus;
use App\Events\TechnicalPlanStatusChanged;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Notifications\TechnicalPlanReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TechnicalPlanAdminTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_the_overview_sorts_the_newest_performance_first(): void
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

    public function test_the_edit_all_ability_is_shared_with_the_frontend(): void
    {
        $this->actingAs($this->technician())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.editAllTechnicalPlans', true));

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.editAllTechnicalPlans', false));
    }

    public function test_guests_cannot_open_a_plans_details(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->get(route('technical-plans.show', $plan))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_the_technician_role_cannot_open_a_plans_details(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('technical-plans.show', $plan))
            ->assertForbidden();
    }

    public function test_technicians_can_open_a_plans_details(): void
    {
        $author = User::factory()->create(['name' => 'Mart Naide', 'email' => 'mart@naide.ee']);
        $team = Team::factory()->create(['name' => 'Märold']);
        $show = Show::factory()->create(['team_id' => $team->id, 'name' => 'Festival 2026']);
        $performance = Performance::factory()->create(['show_id' => $show->id, 'date' => '2026-08-01']);

        $plan = TechnicalPlan::factory()->submitted()->create([
            'user_id' => $author->id,
            'performance_id' => $performance->id,
            'submitted_at' => '2026-07-20 12:00:00',
        ]);

        $this->actingAs($this->technician())
            ->get(route('technical-plans.show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('technical-plans/Show')
                ->where('plan.token', $plan->token)
                ->where('plan.showName', 'Festival 2026')
                ->where('plan.teamName', 'Märold')
                ->where('plan.performanceDate', '2026-08-01')
                ->where('plan.submittedBy', 'Mart Naide')
                ->where('plan.submittedByEmail', 'mart@naide.ee')
                ->where('plan.status', TechnicalPlanStatus::Submitted->value)
                ->where('plan.statusLabel', TechnicalPlanStatus::Submitted->label())
                ->where('plan.submittedAt', '2026-07-20')
                ->where('plan.url', route('technical-plan.public', $plan))
                ->has('statuses', count(TechnicalPlanStatus::cases())));
    }

    public function test_guests_cannot_change_a_plans_status(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->patch(route('technical-plans.update-status', $plan), ['status' => TechnicalPlanStatus::Received->value])
            ->assertRedirect(route('login'));
    }

    public function test_users_without_the_permission_cannot_change_a_plans_status(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('technical-plans.update-status', $plan), ['status' => TechnicalPlanStatus::Received->value])
            ->assertForbidden();

        $this->assertSame(TechnicalPlanStatus::Submitted, $plan->fresh()->status);
    }

    public function test_technicians_can_change_a_plans_status(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->actingAs($this->technician())
            ->patch(route('technical-plans.update-status', $plan), ['status' => TechnicalPlanStatus::Received->value])
            ->assertRedirect(route('technical-plans.show', $plan));

        $this->assertSame(TechnicalPlanStatus::Received, $plan->fresh()->status);
    }

    public function test_changing_a_plans_status_dispatches_an_event(): void
    {
        Event::fake([TechnicalPlanStatusChanged::class]);

        $plan = TechnicalPlan::factory()->submitted()->create();
        $technician = $this->technician();

        $this->actingAs($technician)
            ->patch(route('technical-plans.update-status', $plan), ['status' => TechnicalPlanStatus::Received->value]);

        Event::assertDispatched(
            TechnicalPlanStatusChanged::class,
            fn (TechnicalPlanStatusChanged $event): bool => $event->plan->is($plan)
                && $event->previousStatus === TechnicalPlanStatus::Submitted
                && $event->newStatus === TechnicalPlanStatus::Received
                && $event->changedBy->is($technician),
        );
    }

    public function test_moving_a_plan_from_submitted_to_received_mails_its_author(): void
    {
        Notification::fake();
        config(['technical_plan.tech_email' => 'tehnikud@ruutu10.ee']);

        $author = User::factory()->create();
        $technician = $this->technician();
        $plan = TechnicalPlan::factory()->submitted()->create(['user_id' => $author->id]);

        $this->actingAs($technician)
            ->patch(route('technical-plans.update-status', $plan), ['status' => TechnicalPlanStatus::Received->value]);

        Notification::assertSentTo(
            $author,
            fn (TechnicalPlanReceived $notification): bool => $notification->plan->is($plan)
                && $notification->confirmedBy->is($technician),
        );
    }

    public function test_a_plan_with_no_author_mails_no_one_when_received(): void
    {
        Notification::fake();

        $plan = TechnicalPlan::factory()->submitted()->create(['user_id' => null]);

        $this->actingAs($this->technician())
            ->patch(route('technical-plans.update-status', $plan), ['status' => TechnicalPlanStatus::Received->value]);

        Notification::assertNothingSent();
    }

    public function test_moving_a_plan_between_any_other_statuses_mails_no_one(): void
    {
        Notification::fake();

        $draft = TechnicalPlan::factory()->create(['status' => TechnicalPlanStatus::Draft]);
        $received = TechnicalPlan::factory()->create(['status' => TechnicalPlanStatus::Received]);

        $this->actingAs($this->technician())
            ->patch(route('technical-plans.update-status', $draft), ['status' => TechnicalPlanStatus::Submitted->value]);

        $this->actingAs($this->technician())
            ->patch(route('technical-plans.update-status', $received), ['status' => TechnicalPlanStatus::Archived->value]);

        Notification::assertNothingSent();
    }

    public function test_the_received_mail_carries_the_plan_its_status_and_who_confirmed_it(): void
    {
        config(['technical_plan.tech_email' => 'tehnikud@ruutu10.ee']);

        $author = User::factory()->create(['email' => 'mart@naide.ee']);
        $confirmedBy = User::factory()->create(['name' => 'Tiit Tehnik']);
        $team = Team::factory()->create();
        $show = Show::factory()->create(['team_id' => $team->id, 'name' => 'Festival 2026']);
        $performance = Performance::factory()->create(['show_id' => $show->id, 'date' => '2026-08-10']);
        $plan = TechnicalPlan::factory()->create([
            'status' => TechnicalPlanStatus::Received,
            'user_id' => $author->id,
            'performance_id' => $performance->id,
        ]);

        $mail = (new TechnicalPlanReceived($plan, $confirmedBy))->toMail($author);
        $html = $mail->render();

        $this->assertStringContainsString('Festival 2026', $mail->subject);
        $this->assertStringContainsString('Festival 2026', $html);
        $this->assertStringContainsString(route('technical-plan.public', $plan), $html);
        $this->assertStringContainsString(TechnicalPlanStatus::Received->label(), $html);
        $this->assertStringContainsString('Tiit Tehnik', $html);
        $this->assertSame([['tehnikud@ruutu10.ee', null]], $mail->cc);
    }

    public function test_the_received_mail_does_not_cc_the_author_who_is_the_technical_team(): void
    {
        $author = User::factory()->create(['email' => 'tehnikud@ruutu10.ee']);
        config(['technical_plan.tech_email' => 'tehnikud@ruutu10.ee']);

        $plan = TechnicalPlan::factory()->create(['status' => TechnicalPlanStatus::Received, 'user_id' => $author->id]);

        $mail = (new TechnicalPlanReceived($plan, $author))->toMail($author);

        $this->assertSame([], $mail->cc);
    }

    public function test_a_plans_status_must_be_a_known_value(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->actingAs($this->technician())
            ->patch(route('technical-plans.update-status', $plan), ['status' => 'not-a-status'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TechnicalPlanStatus::Submitted, $plan->fresh()->status);
    }
}
