<?php

namespace Tests\Feature;

use App\Enums\ReminderSchedule;
use App\Enums\TeamRole;
use App\Enums\TechnicalPlanStatus;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Notifications\TechnicalPlanMissing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Chasing performers for a technical plan that has not been handed in.
 *
 * The two things worth being sure of are that the right people are written to
 * at the right moment, and that nobody is written to twice — a reminder that
 * arrives in duplicate is one that stops being read.
 */
class PerformanceReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every "six days before" in here is measured from this, so the tests
        // say what they mean rather than what today happens to be.
        CarbonImmutable::setTestNow('2026-09-01 12:00:00');

        config(['technical_plan.tech_email' => 'tehnik@ruutu10.ee']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_chases_every_member_of_the_performing_group_six_days_out(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        foreach ($members as $member) {
            Notification::assertSentTo(
                $member,
                TechnicalPlanMissing::class,
                fn (TechnicalPlanMissing $mail): bool => $mail->performance->is($performance)
                    && $mail->schedule === ReminderSchedule::SixDays,
            );
        }

        $this->assertDatabaseHas('performance_reminders', [
            'performance_id' => $performance->id,
            'schedule' => ReminderSchedule::SixDays->value,
            'recipients' => 2,
        ]);
    }

    public function test_it_chases_again_thirty_hours_out(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceIn('30 hours');

        // The six-day notice has already gone out, as it would have five days
        // ago; this is the second letter.
        $performance->reminders()->create([
            'schedule' => ReminderSchedule::SixDays,
            'sent_at' => now()->subDays(5),
            'recipients' => 2,
        ]);

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        // Two performers and the crew.
        Notification::assertSentTimes(TechnicalPlanMissing::class, 3);

        Notification::assertSentTo(
            $members[0],
            TechnicalPlanMissing::class,
            fn (TechnicalPlanMissing $mail): bool => $mail->schedule === ReminderSchedule::ThirtyHours,
        );

        // The count is the performers chased; the crew's copy is not one of
        // them, being told rather than asked.
        $this->assertDatabaseHas('performance_reminders', [
            'performance_id' => $performance->id,
            'schedule' => ReminderSchedule::ThirtyHours->value,
            'recipients' => 2,
        ]);
    }

    public function test_a_reminder_that_is_not_due_yet_waits(): void
    {
        Notification::fake();

        [$performance] = $this->performanceIn('8 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('performance_reminders', 0);

        // Two days later the six-day moment has arrived.
        CarbonImmutable::setTestNow(now()->addDays(2)->addMinute());

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertSentTimes(TechnicalPlanMissing::class, 3);
        $this->assertDatabaseHas('performance_reminders', [
            'performance_id' => $performance->id,
            'schedule' => ReminderSchedule::SixDays->value,
        ]);
    }

    public function test_running_it_again_chases_nobody_twice(): void
    {
        Notification::fake();

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();
        $this->artisan('performances:remind-missing-plans')->assertSuccessful();
        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        // Two performers and the crew, one letter each, however often the job
        // runs.
        Notification::assertSentTimes(TechnicalPlanMissing::class, 3);
    }

    public function test_the_technical_team_gets_a_reminder_of_its_own(): void
    {
        Notification::fake();

        [$performance] = $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertSentOnDemand(
            TechnicalPlanMissing::class,
            fn (TechnicalPlanMissing $mail, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'tehnik@ruutu10.ee'
                && $mail->performance->is($performance)
                && ! $mail->isForPerformer(),
        );

        // Two performers and the crew: three letters, three separate messages.
        Notification::assertSentTimes(TechnicalPlanMissing::class, 3);
    }

    public function test_the_crews_copy_carries_no_link_that_would_sign_anybody_in(): void
    {
        Notification::fake();

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertSentOnDemand(
            TechnicalPlanMissing::class,
            function (TechnicalPlanMissing $notification): bool {
                // A magic link signs in whoever follows it, so the crew's copy
                // must not hold one — theirs points at the overview, which asks
                // them to sign in as themselves.
                $this->assertNull($notification->planUrl);

                $body = (string) $notification->toMail(new AnonymousNotifiable)->render();

                $this->assertStringNotContainsString('/magiclink/', $body);
                $this->assertStringContainsString(route('technical-plans.index'), $body);

                return true;
            },
        );
    }

    public function test_no_reminder_is_ever_addressed_to_more_than_one_person(): void
    {
        Notification::fake();

        [, $members] = $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        // The whole reason the crew gets its own letter: a performer's carries
        // their login link, so nobody may be copied in on it.
        Notification::assertSentTo(
            $members[0],
            TechnicalPlanMissing::class,
            function (TechnicalPlanMissing $notification) use ($members): bool {
                $mail = $notification->toMail($members[0]);

                $this->assertSame([], $mail->cc);
                $this->assertSame([], $mail->bcc);

                // The performer's copy renders, and it is the one that holds
                // the login link — the crew's copy is checked separately.
                $body = (string) $mail->render();

                $this->assertStringContainsString($notification->planUrl ?? '', $body);
                $this->assertStringContainsString('Täida tehnikaplaan', $body);
                // Nobody else's address appears on it.
                $this->assertStringNotContainsString($members[1]->email, $body);

                return true;
            },
        );
    }

    public function test_the_crews_copy_lists_who_was_chased(): void
    {
        Notification::fake();

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertSentOnDemand(
            TechnicalPlanMissing::class,
            function (TechnicalPlanMissing $notification): bool {
                $this->assertSame(
                    ['jaan@naide.ee', 'mari@naide.ee'],
                    collect($notification->chased)->pluck('email')->sort()->values()->all(),
                );

                return true;
            },
        );
    }

    public function test_without_a_technical_contact_the_performers_are_still_chased(): void
    {
        Notification::fake();
        Log::spy();

        config(['technical_plan.tech_email' => '']);

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        // The performers hear about it either way; the gap is worth a line in
        // the log rather than a failed run.
        Notification::assertSentTimes(TechnicalPlanMissing::class, 2);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => $message === 'No technical contact configured; a missing plan was chased without the crew hearing about it');
    }

    public function test_the_reminder_carries_a_link_that_opens_the_plan_for_that_night(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        // Following it once signs the performer in and hands them on to the
        // wizard, told which night it is about and to skip the step that would
        // have been for choosing it.
        $landedOn = $this->get($this->sentLinkFor($members[0]))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertAuthenticatedAs($members[0]);
        $this->assertStringContainsString('performance='.$performance->id, (string) $landedOn);
        $this->assertStringContainsString('step=1', (string) $landedOn);

        // And it really does open on the plan, with the night filled in.
        $this->get($landedOn)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TechnicalPlan')
                ->where('initialStep', 1)
                ->where('initialPerformance.performanceId', $performance->id)
                ->where('initialPerformance.showName', 'Öine impro'));
    }

    public function test_a_link_naming_a_performance_that_has_been_played_opens_at_the_beginning(): void
    {
        [$performance, $members] = $this->performanceIn('6 days');

        $wizard = route('technical-plan.index', ['performance' => $performance->id, 'step' => 1]);

        // The night comes and goes while the mail sits in an inbox.
        CarbonImmutable::setTestNow(now()->addDays(7));

        $this->actingAs($members[0])
            ->get($wizard)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TechnicalPlan')
                ->where('initialPerformance', null)
                // Without a night to open on, the step is ignored too: the
                // performer lands on the picker rather than on a blank form.
                ->where('initialStep', 0));
    }

    public function test_a_link_naming_a_performance_put_back_to_draft_opens_at_the_beginning(): void
    {
        [$performance, $members] = $this->performanceIn('6 days');

        $performance->update(['is_draft' => true]);

        $this->actingAs($members[0])
            ->get(route('technical-plan.index', ['performance' => $performance->id, 'step' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('initialPerformance', null)
                ->where('initialStep', 0));
    }

    public function test_a_link_asking_for_a_step_that_does_not_exist_is_held_to_one_that_does(): void
    {
        [$performance, $members] = $this->performanceIn('6 days');

        $this->actingAs($members[0])
            ->get(route('technical-plan.index', ['performance' => $performance->id, 'step' => 99]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('initialStep', 6));
    }

    public function test_a_performance_with_a_handed_in_plan_is_left_alone(): void
    {
        Notification::fake();

        [$performance] = $this->performanceIn('6 days');

        TechnicalPlan::factory()->create([
            'performance_id' => $performance->id,
            'status' => TechnicalPlanStatus::Submitted,
        ]);

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('performance_reminders', 0);
    }

    public function test_a_plan_still_being_written_does_not_count_as_handed_in(): void
    {
        Notification::fake();

        [$performance] = $this->performanceIn('6 days');

        TechnicalPlan::factory()->create([
            'performance_id' => $performance->id,
            'status' => TechnicalPlanStatus::Draft,
        ]);

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertSentTimes(TechnicalPlanMissing::class, 3);
    }

    public function test_a_draft_performance_is_not_chased(): void
    {
        Notification::fake();

        $this->performanceIn('6 days', draft: true);

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_show_without_an_owning_group_is_not_chased(): void
    {
        Notification::fake();

        $show = Show::factory()->create(['team_id' => null]);

        Performance::factory()->create([
            'show_id' => $show->id,
            'date' => now()->addDays(6)->subMinute(),
            'created_at' => now()->subMonths(3),
        ]);

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('performance_reminders', 0);
    }

    public function test_a_performance_already_being_played_is_not_chased(): void
    {
        Notification::fake();

        $this->performanceIn('-1 hour');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_window_that_had_passed_before_the_performance_was_registered_is_written_off(): void
    {
        Notification::fake();
        Log::spy();

        // Put on the books two days before it happens: the six-day notice was
        // never possible, so it is recorded as dealt with rather than sent.
        [$performance] = $this->performanceIn('2 days', registeredAt: 'now');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();

        $reminder = $performance->reminders()->where('schedule', ReminderSchedule::SixDays)->sole();

        $this->assertNull($reminder->sent_at);
        $this->assertSame(0, $reminder->recipients);

        // And the thirty-hour one is still to come, not written off with it.
        $this->assertDatabaseMissing('performance_reminders', [
            'performance_id' => $performance->id,
            'schedule' => ReminderSchedule::ThirtyHours->value,
        ]);

        CarbonImmutable::setTestNow(now()->addHours(19));

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertSentTimes(TechnicalPlanMissing::class, 3);
    }

    public function test_a_run_catching_up_after_downtime_sends_one_letter_rather_than_both(): void
    {
        Notification::fake();
        Log::spy();

        // On the books for months, but nothing has run since before the
        // six-day moment — and by now the thirty-hour one is due as well.
        [$performance, $members] = $this->performanceIn('20 hours');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        // One letter each, not two: the six-day notice has been overtaken and
        // is written off, and only the thirty-hour one is worth sending now.
        Notification::assertSentTimes(TechnicalPlanMissing::class, 3);

        Notification::assertSentTo(
            $members[0],
            TechnicalPlanMissing::class,
            fn (TechnicalPlanMissing $mail): bool => $mail->schedule === ReminderSchedule::ThirtyHours,
        );

        $this->assertNull(
            $performance->reminders()->where('schedule', ReminderSchedule::SixDays)->sole()->sent_at,
        );
        $this->assertNotNull(
            $performance->reminders()->where('schedule', ReminderSchedule::ThirtyHours)->sole()->sent_at,
        );

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Wrote off a technical-plan reminder rather than sending it late'
                && $context['reason'] === 'overtaken');
    }

    public function test_a_group_with_no_members_is_reported_rather_than_silently_marked_done(): void
    {
        Notification::fake();
        Log::spy();

        $team = Team::factory()->create();
        $show = Show::factory()->create(['team_id' => $team->id]);

        $performance = Performance::factory()->create([
            'show_id' => $show->id,
            'date' => now()->addDays(6)->subMinute(),
            'created_at' => now()->subMonths(3),
        ]);

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();

        // The claim is given back, so somebody joining the group in time still
        // gets chased.
        $this->assertDatabaseCount('performance_reminders', 0);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'A performance needs its technical plan chased, but its group has no members'
                && $context['performance_id'] === $performance->id);
    }

    public function test_a_dry_run_mails_nobody_and_records_nothing(): void
    {
        Notification::fake();

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans', ['--dry-run' => true])
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('performance_reminders', 0);
    }

    public function test_a_backfill_writes_off_what_is_due_without_mailing(): void
    {
        Notification::fake();

        [$performance] = $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans', ['--backfill' => true])
            ->assertSuccessful();

        Notification::assertNothingSent();

        $this->assertDatabaseHas('performance_reminders', [
            'performance_id' => $performance->id,
            'schedule' => ReminderSchedule::SixDays->value,
            'sent_at' => null,
        ]);

        // And having been written off, it is not sent afterwards either.
        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_reminders_can_be_switched_off_entirely(): void
    {
        Notification::fake();

        config(['performance.reminders.enabled' => false]);

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('performance_reminders', 0);
    }

    public function test_the_run_records_what_it_did(): void
    {
        Notification::fake();
        Log::spy();

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-plans')->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Technical-plan reminder run finished'
                && $context['performances_due'] === 1
                && $context['performers_mailed'] === 2);
    }

    /**
     * A vouched-for performance the given interval away, owned by a group of
     * two, with no plan handed in for it.
     *
     * Registered a season ago unless told otherwise: when a performance went on
     * the books decides whether a reminder is chased or written off, so a test
     * about the chasing has to put it there in good time. Passing
     * `registeredAt: 'now'` is how the late-addition case is set up.
     *
     * @return array{0: Performance, 1: array<int, User>}
     */
    private function performanceIn(string $interval, bool $draft = false, string $registeredAt = '-3 months'): array
    {
        $team = Team::factory()->create();

        $members = [
            User::factory()->create(['email' => 'mari@naide.ee']),
            User::factory()->create(['email' => 'jaan@naide.ee']),
        ];

        foreach ($members as $index => $member) {
            $team->members()->attach($member, [
                'role' => $index === 0 ? TeamRole::Owner->value : TeamRole::Member->value,
            ]);
        }

        $show = Show::factory()->create(['team_id' => $team->id, 'name' => 'Öine impro']);

        $performance = Performance::factory()->when($draft, fn ($factory) => $factory->draft())->create([
            'show_id' => $show->id,
            // A minute inside the window, so "six days out" is due rather than
            // due in a moment.
            'date' => now()->add($interval)->subMinute(),
            'created_at' => now()->modify($registeredAt),
        ]);

        return [$performance, $members];
    }

    /**
     * The magic link the given performer was actually mailed.
     */
    private function sentLinkFor(User $performer): string
    {
        $link = '';

        Notification::assertSentTo(
            $performer,
            TechnicalPlanMissing::class,
            function (TechnicalPlanMissing $mail) use (&$link): bool {
                $link = $mail->planUrl;

                return true;
            },
        );

        return $link;
    }
}
