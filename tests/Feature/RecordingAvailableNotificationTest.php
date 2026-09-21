<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Events\PerformanceRecordingLinked;
use App\Listeners\NotifyRecordingAvailable;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Notifications\PerformanceRecordingAvailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Telling whoever wrote a night's technical plan that there is now a video of
 * it.
 *
 * Once ever is the rule worth the tests: a crew member correcting a mistyped
 * address should not write to the performers a second time about the same
 * evening, and neither should a queued job retried after a crash.
 */
class RecordingAvailableNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jellyfin.url', 'https://jellyfin.test');
        config()->set('services.jellyfin.api_key', 'test-key');

        // The push is a listener of its own on the same event; this file is
        // about the letter, so the library is walled off rather than faked.
        Http::preventStrayRequests();
        Http::fake([
            'jellyfin.test/Items?*' => Http::response([
                'Items' => [['Id' => self::ITEM_ID, 'Type' => 'Episode']],
                'TotalRecordCount' => 1,
            ]),
            'jellyfin.test/*' => Http::response(null, 204),
        ]);
    }

    public function test_the_plan_author_is_told_a_video_is_available(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $recording = $this->recordingFor($performance);

        $this->announce($recording);

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->recording->is($recording),
        );

        $this->assertNotNull($recording->fresh()->announced_at);
    }

    public function test_the_people_on_stage_are_blind_copied(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $player = $this->staff($performance, PerformanceStaffRole::Performer);
        $host = $this->staff($performance, PerformanceStaffRole::Host);

        $this->announce($this->recordingFor($performance));

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [
                $host->email,
                $player->email,
            ] || $notification->blindCopies === [
                $player->email,
                $host->email,
            ],
        );
    }

    /**
     * The desk, the camera, the door and the bar kept the night running from
     * the side of it. The recording is not of them.
     */
    public function test_the_crew_behind_the_night_are_not_copied(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $player = $this->staff($performance, PerformanceStaffRole::Performer);
        $this->staff($performance, PerformanceStaffRole::Technician);
        $this->staff($performance, PerformanceStaffRole::VideoOperator);
        $this->staff($performance, PerformanceStaffRole::TicketSeller);
        $this->staff($performance, PerformanceStaffRole::Bar);

        $this->announce($this->recordingFor($performance));

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [$player->email],
        );
    }

    /**
     * Somebody who wrote the plan and then played in the show is written to
     * once, addressed to them, rather than openly and blindly at the same time.
     */
    public function test_an_author_who_was_also_on_stage_is_not_copied_as_well(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $performance->staff()->attach($author, ['role' => PerformanceStaffRole::Performer->value]);

        $this->announce($this->recordingFor($performance));

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [],
        );
    }

    /**
     * A shared evening has a plan per act, and the people on stage should not
     * be sent the same news once per plan somebody else wrote.
     */
    public function test_the_blind_copy_rides_on_one_letter_only(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $first = User::factory()->ofTheHouse()->create();
        $second = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($first, 'user')->create();
        TechnicalPlan::factory()->for($performance)->for($second, 'user')->create();

        $player = $this->staff($performance, PerformanceStaffRole::Performer);

        $this->announce($this->recordingFor($performance));

        $carrying = 0;

        foreach ([$first, $second] as $author) {
            Notification::assertSentTo(
                $author,
                PerformanceRecordingAvailable::class,
                function (PerformanceRecordingAvailable $notification) use (&$carrying, $player): bool {
                    if ($notification->blindCopies === [$player->email]) {
                        $carrying++;
                    }

                    return true;
                },
            );
        }

        $this->assertSame(1, $carrying, 'The people on stage should be blind-copied exactly once.');
    }

    public function test_the_letter_carries_the_blind_copies_as_bcc(): void
    {
        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $player = $this->staff($performance, PerformanceStaffRole::Performer);

        $mail = (new PerformanceRecordingAvailable(
            $this->recordingFor($performance),
            [$player->email],
        ))->toMail($author);

        $this->assertSame([[$player->email, null]], $mail->bcc);
    }

    public function test_a_night_with_nobody_on_stage_writes_to_its_author_alone(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $this->announce($this->recordingFor($performance));

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [],
        );
    }

    /**
     * The letter carries a link into a library only the house can open, so
     * sending it to a visiting performer's private address would be telling
     * somebody about a video they cannot watch.
     */
    public function test_an_author_on_an_outside_address_is_not_written_to(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $guest = User::factory()->create(['email' => 'guest@gmail.com']);
        TechnicalPlan::factory()->for($performance)->for($guest, 'user')->create();

        $this->announce($this->recordingFor($performance));

        Notification::assertNothingSent();
    }

    public function test_somebody_on_stage_on_an_outside_address_is_not_copied(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $ours = $this->staff($performance, PerformanceStaffRole::Performer);
        $guest = User::factory()->create(['email' => 'guest@gmail.com']);
        $performance->staff()->attach($guest, ['role' => PerformanceStaffRole::Performer->value]);

        $this->announce($this->recordingFor($performance));

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [$ours->email],
        );
    }

    /**
     * Only one of the evening's two plans was written by somebody the house can
     * write to, so that is the one letter that goes — carrying the blind copy.
     */
    public function test_an_outside_author_does_not_take_the_blind_copy_with_them(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $guest = User::factory()->create(['email' => 'guest@gmail.com']);
        $ours = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($guest, 'user')->create();
        TechnicalPlan::factory()->for($performance)->for($ours, 'user')->create();

        $player = $this->staff($performance, PerformanceStaffRole::Performer);

        $this->announce($this->recordingFor($performance));

        Notification::assertCount(1);
        Notification::assertSentTo(
            $ours,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [$player->email],
        );
    }

    /**
     * The letter is a plan author's; a night nobody filed a plan for has nobody
     * to address it to, and the people on stage are not written to on their own.
     */
    public function test_nobody_is_copied_when_there_is_no_plan_author(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $this->staff($performance, PerformanceStaffRole::Performer);

        $this->announce($this->recordingFor($performance));

        Notification::assertNothingSent();
    }

    public function test_two_plans_by_one_author_are_one_letter(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->count(2)->for($performance)->for($author, 'user')->create();

        $this->announce($this->recordingFor($performance));

        Notification::assertSentToTimes($author, PerformanceRecordingAvailable::class, 1);
    }

    public function test_every_author_of_a_shared_evening_is_told(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $first = User::factory()->ofTheHouse()->create();
        $second = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($first, 'user')->create();
        TechnicalPlan::factory()->for($performance)->for($second, 'user')->create();

        $this->announce($this->recordingFor($performance));

        Notification::assertSentTo($first, PerformanceRecordingAvailable::class);
        Notification::assertSentTo($second, PerformanceRecordingAvailable::class);
        Notification::assertCount(2);
    }

    /**
     * The reason `announced_at` exists at all.
     */
    public function test_a_corrected_link_sends_no_second_letter(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $recording = $this->recordingFor($performance);

        $this->announce($recording);
        $this->announce($recording->fresh());

        Notification::assertCount(1);
    }

    public function test_a_night_nobody_wrote_a_plan_for_writes_to_nobody(): void
    {
        Notification::fake();

        $this->announce($this->recordingFor(Performance::factory()->create()));

        Notification::assertNothingSent();
    }

    public function test_a_plan_with_no_author_is_passed_over(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        TechnicalPlan::factory()->for($performance)->create(['user_id' => null]);

        $this->announce($this->recordingFor($performance));

        Notification::assertNothingSent();
    }

    public function test_the_letter_points_at_the_episode_in_the_library(): void
    {
        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create();

        $recording = $this->recordingFor($performance);

        $mail = (new PerformanceRecordingAvailable($recording))->toMail($author);

        $this->assertStringContainsString($performance->format->name, $mail->subject);
        $this->assertSame(
            'https://jellyfin.test/web/#/details?id='.self::ITEM_ID,
            $mail->viewData['recordingUrl'],
        );
        $this->assertSame($performance->displayName(), $mail->viewData['formatName']);
    }

    public function test_the_letter_is_queued_rather_than_sent_in_the_request(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new NotifyRecordingAvailable);
        $this->assertInstanceOf(
            ShouldQueue::class,
            new PerformanceRecordingAvailable(new PerformanceRecording),
        );
    }

    public function test_the_announcement_is_recorded_in_the_audit_trail(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        TechnicalPlan::factory()->for($performance)->for(User::factory()->ofTheHouse(), 'user')->create();

        $recording = $this->recordingFor($performance);
        $crew = $this->technician();

        $this->announce($recording, $crew);

        $activity = Activity::query()->forSubject($recording)->forEvent('recording_announced')->sole();

        $this->assertTrue($crew->is($activity->causer));
        $this->assertSame(1, $activity->getProperty('recipients'));
    }

    /**
     * Fire the event for real on the sync queue the suite runs, so the listener
     * is exercised rather than described.
     */
    private function announce(PerformanceRecording $recording, ?User $linkedBy = null): void
    {
        PerformanceRecordingLinked::dispatch($recording, $linkedBy ?? $this->technician());
    }

    /**
     * Put somebody on the night's staff list in the given role, as the Planka
     * import does.
     */
    private function staff(Performance $performance, PerformanceStaffRole $role): User
    {
        $member = User::factory()->ofTheHouse()->create();

        $performance->staff()->attach($member, ['role' => $role->value]);

        return $member;
    }

    private function recordingFor(Performance $performance): PerformanceRecording
    {
        return PerformanceRecording::factory()
            ->for($performance)
            ->forItem(self::ITEM_ID)
            ->create();
    }
}
