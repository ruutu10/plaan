<?php

namespace Tests\Feature;

use App\Enums\TechnicalPlanStatus;
use App\Events\TechnicalPlanStatusChanged;
use App\Events\TechnicalPlanSubmitted;
use App\Models\Format;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The audit trail spatie/laravel-activitylog keeps for the application's main
 * models: who did what, and — for whatever changed with nobody signed in —
 * that it was the system itself.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_format_logs_the_signed_in_user_as_the_causer(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $this->actingAs($user)->postJson(route('api.formats.store'), [
            'team_id' => $team->id,
            'name' => 'Hooaja avaetendus',
        ])->assertCreated();

        $format = Format::query()->where('name', 'Hooaja avaetendus')->firstOrFail();

        $activity = Activity::query()->forSubject($format)->forEvent('created')->sole();

        $this->assertTrue($user->is($activity->causer));
        $this->assertStringContainsString($user->name, $activity->description);
        $this->assertSame($team->id, $activity->attribute_changes->get('attributes')['team_id']);
    }

    public function test_creating_a_model_with_nobody_signed_in_logs_the_system_as_the_causer(): void
    {
        // No actingAs(): nothing is signed in, the way a console import or a
        // queued job runs — see App\Console\Commands\ImportPlankaPerformances.
        $format = Format::factory()->create(['name' => 'Imporditud lavastus']);

        $activity = Activity::query()->forSubject($format)->forEvent('created')->sole();

        $this->assertNull($activity->causer);
        $this->assertStringContainsString('the system', $activity->description);
    }

    public function test_technical_plan_submission_is_logged_as_its_own_event(): void
    {
        $user = User::factory()->create();
        $plan = TechnicalPlan::factory()->create(['user_id' => $user->id, 'status' => TechnicalPlanStatus::Submitted]);

        $this->actingAs($user);
        event(new TechnicalPlanSubmitted($plan));

        $activity = Activity::query()->forSubject($plan)->forEvent('submitted')->sole();

        $this->assertTrue($user->is($activity->causer));
        $this->assertStringContainsString($user->name, $activity->description);
        $this->assertSame($plan->performance_id, $activity->getProperty('performance_id'));
    }

    public function test_routine_draft_saves_are_not_logged(): void
    {
        $plan = TechnicalPlan::factory()->create();

        $plan->update(['submitted_at' => null]);

        $this->assertSame(0, Activity::query()->forSubject($plan)->forEvent('updated')->count());
    }

    public function test_technical_plan_status_change_is_logged_with_the_technician_as_causer(): void
    {
        $technician = $this->technician();
        $plan = TechnicalPlan::factory()->create(['status' => TechnicalPlanStatus::Submitted]);

        $this->actingAs($technician);
        event(new TechnicalPlanStatusChanged(
            $plan,
            TechnicalPlanStatus::Submitted,
            TechnicalPlanStatus::Received,
            $technician,
        ));

        $activity = Activity::query()->forSubject($plan)->forEvent('status_changed')->sole();

        $this->assertTrue($technician->is($activity->causer));
        $this->assertSame('submitted', $activity->getProperty('from'));
        $this->assertSame('received', $activity->getProperty('to'));
    }

    public function test_self_registration_logs_the_new_account_as_a_system_action(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $activity = Activity::query()->forSubject($user)->forEvent('created')->sole();

        // Nobody is signed in yet at the moment an account is created by
        // self-registration — the account only logs in afterwards.
        $this->assertNull($activity->causer);
        $this->assertStringContainsString('the system', $activity->description);
        $this->assertArrayNotHasKey('password', $activity->attribute_changes->get('attributes'));
    }
}
