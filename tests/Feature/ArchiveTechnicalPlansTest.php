<?php

namespace Tests\Feature;

use App\Enums\TechnicalPlanStatus;
use App\Models\Performance;
use App\Models\Show;
use App\Models\TechnicalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTechnicalPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every "two days ago" in here is measured from this, so the tests say
        // what they mean rather than what today happens to be.
        CarbonImmutable::setTestNow('2026-09-01 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * A plan of the given status, for a performance that started the given
     * number of hours ago — negative for a night still to come.
     */
    private function planFor(TechnicalPlanStatus $status, int $startedHoursAgo): TechnicalPlan
    {
        $performance = Performance::factory()
            ->for(Show::factory())
            ->create(['date' => now()->subHours($startedHoursAgo)]);

        return TechnicalPlan::factory()
            ->for($performance)
            ->create(['status' => $status, 'submitted_at' => now()->subHours($startedHoursAgo + 24)]);
    }

    public function test_it_archives_the_plans_of_a_performance_already_played(): void
    {
        $submitted = $this->planFor(TechnicalPlanStatus::Submitted, startedHoursAgo: 48);
        $received = $this->planFor(TechnicalPlanStatus::Received, startedHoursAgo: 48);

        $this->artisan('technical-plans:archive')->assertSuccessful();

        $this->assertSame(TechnicalPlanStatus::Archived, $submitted->refresh()->status);
        $this->assertSame(TechnicalPlanStatus::Archived, $received->refresh()->status);
    }

    public function test_it_leaves_a_plan_alone_within_the_grace_period(): void
    {
        // Last night's show is still the crew's business the morning after.
        $plan = $this->planFor(TechnicalPlanStatus::Submitted, startedHoursAgo: 2);

        $this->artisan('technical-plans:archive')->assertSuccessful();

        $this->assertSame(TechnicalPlanStatus::Submitted, $plan->refresh()->status);
    }

    public function test_it_leaves_the_plans_of_upcoming_performances_alone(): void
    {
        $plan = $this->planFor(TechnicalPlanStatus::Submitted, startedHoursAgo: -168);

        $this->artisan('technical-plans:archive')->assertSuccessful();

        $this->assertSame(TechnicalPlanStatus::Submitted, $plan->refresh()->status);
    }

    public function test_it_leaves_drafts_alone(): void
    {
        // Nobody ever handed this in; there is nothing to archive, however long
        // ago the night was.
        $draft = $this->planFor(TechnicalPlanStatus::Draft, startedHoursAgo: 720);

        $this->artisan('technical-plans:archive')->assertSuccessful();

        $this->assertSame(TechnicalPlanStatus::Draft, $draft->refresh()->status);
    }

    public function test_it_leaves_a_plan_whose_performance_was_put_aside_alone(): void
    {
        // No night to have passed, so no moment at which it is done with.
        $plan = TechnicalPlan::factory()->submitted()->create();
        $plan->performance->delete();

        $this->artisan('technical-plans:archive')->assertSuccessful();

        $this->assertSame(TechnicalPlanStatus::Submitted, $plan->refresh()->status);
    }

    public function test_the_grace_period_is_configurable(): void
    {
        $plan = $this->planFor(TechnicalPlanStatus::Submitted, startedHoursAgo: 2);

        $this->artisan('technical-plans:archive', ['--hours' => 1])->assertSuccessful();

        $this->assertSame(TechnicalPlanStatus::Archived, $plan->refresh()->status);
    }

    public function test_a_dry_run_names_the_plan_without_archiving_it(): void
    {
        $plan = $this->planFor(TechnicalPlanStatus::Submitted, startedHoursAgo: 48);

        $this->artisan('technical-plans:archive', ['--dry-run' => true])
            ->expectsOutputToContain($plan->token)
            ->assertSuccessful();

        $this->assertSame(TechnicalPlanStatus::Submitted, $plan->refresh()->status);
    }

    public function test_the_command_is_scheduled_to_run_daily(): void
    {
        // Bootstraps the console kernel, which is what loads the schedule.
        $this->artisan('schedule:list')
            ->expectsOutputToContain('technical-plans:archive')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'technical-plans:archive'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }
}
