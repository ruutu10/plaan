<?php

namespace Tests\Feature;

use App\Enums\PerformanceStatus;
use App\Models\Format;
use App\Models\Performance;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivePerformancesTest extends TestCase
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
     * A performance of the given standing that started the given number of
     * hours ago — negative for a night still to come.
     */
    private function performance(PerformanceStatus $status, int $startedHoursAgo): Performance
    {
        return Performance::factory()
            ->for(Format::factory())
            ->create([
                'status' => $status,
                'date' => now()->subHours($startedHoursAgo),
            ]);
    }

    public function test_it_archives_a_performance_already_played(): void
    {
        $played = $this->performance(PerformanceStatus::Upcoming, startedHoursAgo: 48);

        $this->artisan('performances:archive')->assertSuccessful();

        $this->assertSame(PerformanceStatus::Archived, $played->refresh()->status);
    }

    public function test_it_leaves_a_performance_alone_within_the_grace_period(): void
    {
        // Last night's show is still last night's until the day is out; a run
        // during it must not file it away mid-performance.
        $tonight = $this->performance(PerformanceStatus::Upcoming, startedHoursAgo: 2);

        $this->artisan('performances:archive')->assertSuccessful();

        $this->assertSame(PerformanceStatus::Upcoming, $tonight->refresh()->status);
    }

    public function test_it_leaves_performances_still_to_come_alone(): void
    {
        $upcoming = $this->performance(PerformanceStatus::Upcoming, startedHoursAgo: -168);

        $this->artisan('performances:archive')->assertSuccessful();

        $this->assertSame(PerformanceStatus::Upcoming, $upcoming->refresh()->status);
    }

    public function test_it_leaves_drafts_alone(): void
    {
        // Nobody ever vouched for this one, so there is no night to have been
        // played — it still needs reviewing, however long ago the card said.
        $draft = $this->performance(PerformanceStatus::Draft, startedHoursAgo: 720);

        $this->artisan('performances:archive')->assertSuccessful();

        $this->assertSame(PerformanceStatus::Draft, $draft->refresh()->status);
    }

    public function test_it_leaves_the_stand_in_performance_alone(): void
    {
        $placeholder = Performance::placeholder();
        $placeholder->update(['date' => now()->subYears(1)]);

        $this->artisan('performances:archive')->assertSuccessful();

        $this->assertSame(PerformanceStatus::Upcoming, $placeholder->refresh()->status);
    }

    public function test_an_archived_performance_is_still_one_a_plan_can_be_filed_under(): void
    {
        $this->performance(PerformanceStatus::Upcoming, startedHoursAgo: 48);

        $this->artisan('performances:archive')->assertSuccessful();

        // Archiving tidies the bill; it does not disown the night.
        $this->assertSame(1, Performance::query()->excludingPlaceholder()->vouchedFor()->count());
    }

    public function test_the_grace_period_is_configurable(): void
    {
        $tonight = $this->performance(PerformanceStatus::Upcoming, startedHoursAgo: 2);

        $this->artisan('performances:archive', ['--hours' => 1])->assertSuccessful();

        $this->assertSame(PerformanceStatus::Archived, $tonight->refresh()->status);
    }

    public function test_a_dry_run_names_the_performance_without_archiving_it(): void
    {
        $played = $this->performance(PerformanceStatus::Upcoming, startedHoursAgo: 48);

        $this->artisan('performances:archive', ['--dry-run' => true])
            ->expectsOutputToContain($played->displayName())
            ->assertSuccessful();

        $this->assertSame(PerformanceStatus::Upcoming, $played->refresh()->status);
    }

    public function test_the_command_is_scheduled_to_run_weekly(): void
    {
        // Bootstraps the console kernel, which is what loads the schedule.
        $this->artisan('schedule:list')
            ->expectsOutputToContain('performances:archive')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'performances:archive'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * 0', $event->expression);
    }
}
