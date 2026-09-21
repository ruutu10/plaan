<?php

namespace Tests\Feature;

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
        $author = User::factory()->create();
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

    public function test_two_plans_by_one_author_are_one_letter(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->create();
        TechnicalPlan::factory()->count(2)->for($performance)->for($author, 'user')->create();

        $this->announce($this->recordingFor($performance));

        Notification::assertSentToTimes($author, PerformanceRecordingAvailable::class, 1);
    }

    public function test_every_author_of_a_shared_evening_is_told(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
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
        $author = User::factory()->create();
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
        $author = User::factory()->create();
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
        TechnicalPlan::factory()->for($performance)->for(User::factory(), 'user')->create();

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

    private function recordingFor(Performance $performance): PerformanceRecording
    {
        return PerformanceRecording::factory()
            ->for($performance)
            ->forItem(self::ITEM_ID)
            ->create();
    }
}
