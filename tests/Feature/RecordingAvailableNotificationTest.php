<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Events\PerformancePlotSummaryAbandoned;
use App\Events\PerformancePlotSummaryWritten;
use App\Events\PerformanceRecordingLinked;
use App\Listeners\NotifyRecordingAvailable;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Notifications\PerformanceRecordingAvailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Telling whoever wrote a night's technical plan that there is now a video of
 * it, once its plot summary is in.
 *
 * Most of these run with the suite's blank model key, where no summary is ever
 * coming and the letter goes on the link — which keeps them about who is
 * written to rather than when.
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
                'Items' => [[
                    'Id' => self::ITEM_ID,
                    'Type' => 'Episode',
                    'DateCreated' => '2026-04-03T23:36:24.0000000Z',
                    'MediaSources' => [['Path' => '/media/Tšikid Reas S01E04.mp4', 'Size' => 9712902453]],
                ]],
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
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->for($first, 'user')->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($second, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

        $this->announce($this->recordingFor($performance));

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [],
        );
    }

    /**
     * A visiting author can download the video with a login here, so they are
     * written to, without the link into a library they cannot open.
     */
    public function test_an_author_on_an_outside_address_gets_a_download_only_letter(): void
    {
        $performance = Performance::factory()->create();
        $guest = User::factory()->create(['email' => 'guest@gmail.com']);
        TechnicalPlan::factory()->approved()->for($performance)->for($guest, 'user')->create();

        $recording = $this->recordingFor($performance);

        Notification::fake();
        $this->announce($recording);
        Notification::assertSentTo($guest, PerformanceRecordingAvailable::class);

        $mail = (new PerformanceRecordingAvailable($recording))->toMail($guest);

        $this->assertNull($mail->viewData['recordingUrl']);
        $this->assertSame(route('performances.recording.download', $performance), $mail->viewData['downloadUrl']);

        $html = (string) $mail->render();
        $this->assertStringNotContainsString('Vaata Jellyfinis', $html);
        $this->assertStringContainsString('Laadi alla', $html);
    }

    public function test_the_letter_names_the_file_with_its_size_and_date(): void
    {
        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();

        $html = (string) (new PerformanceRecordingAvailable($this->recordingFor($performance)))
            ->toMail($author)
            ->render();

        $this->assertStringContainsString('Tšikid Reas S01E04.mp4', $html);
        $this->assertStringContainsString('9.0 GB', $html);
        $this->assertStringContainsString('lisatud 04.04.2026', $html);
        $this->assertStringNotContainsString('/media/', $html);
    }

    /**
     * How a failed lookup is remembered is covered with the lookup itself;
     * this is only the letter going out without it.
     */
    public function test_a_library_that_does_not_answer_still_lets_the_letter_go(): void
    {
        Cache::put('jellyfin-file:'.self::ITEM_ID, [], 60);

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();

        $html = (string) (new PerformanceRecordingAvailable($this->recordingFor($performance)))
            ->toMail($author)
            ->render();

        $this->assertStringContainsString('Laadi alla', $html);
        $this->assertStringContainsString('mitu gigabaiti', $html);
    }

    public function test_the_house_letter_offers_both_buttons(): void
    {
        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();

        $html = (string) (new PerformanceRecordingAvailable($this->recordingFor($performance)))
            ->toMail($author)
            ->render();

        $this->assertStringContainsString('Vaata Jellyfinis', $html);
        $this->assertStringContainsString('Laadi alla', $html);
    }

    /**
     * Sharing a blind copy would hand a visitor the house's letter, library
     * link and all.
     */
    public function test_somebody_on_stage_on_an_outside_address_gets_a_letter_of_their_own(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

        $ours = $this->staff($performance, PerformanceStaffRole::Performer);
        $guest = User::factory()->create(['email' => 'guest@gmail.com']);
        $performance->staff()->attach($guest, ['role' => PerformanceStaffRole::Performer->value]);

        $this->announce($this->recordingFor($performance));

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [$ours->email],
        );
        Notification::assertSentTo(
            $guest,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [],
        );
        Notification::assertCount(2);
    }

    public function test_with_only_outside_authors_the_house_on_stage_are_written_to_separately(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $guest = User::factory()->create(['email' => 'guest@gmail.com']);
        TechnicalPlan::factory()->approved()->for($performance)->for($guest, 'user')->create();

        $player = $this->staff($performance, PerformanceStaffRole::Performer);

        $this->announce($this->recordingFor($performance));

        Notification::assertCount(2);
        Notification::assertSentTo(
            $player,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [],
        );
    }

    /**
     * Listed first or not, a visitor's letter cannot carry the house's copies.
     */
    public function test_an_outside_author_does_not_take_the_blind_copy_with_them(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $guest = User::factory()->create(['email' => 'guest@gmail.com']);
        $ours = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($guest, 'user')->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($ours, 'user')->create();

        $player = $this->staff($performance, PerformanceStaffRole::Performer);

        $this->announce($this->recordingFor($performance));

        Notification::assertCount(2);
        Notification::assertSentTo(
            $guest,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->blindCopies === [],
        );
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

    public function test_the_author_of_an_unapproved_plan_is_not_written_to(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        TechnicalPlan::factory()->submitted()->for($performance)->for(User::factory()->ofTheHouse(), 'user')->create();
        $this->staff($performance, PerformanceStaffRole::Performer);

        $this->announce($this->recordingFor($performance));

        Notification::assertNothingSent();
    }

    public function test_two_plans_by_one_author_are_one_letter(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->approved()->count(2)->for($performance)->for($author, 'user')->create();

        $this->announce($this->recordingFor($performance));

        Notification::assertSentToTimes($author, PerformanceRecordingAvailable::class, 1);
    }

    public function test_every_author_of_a_shared_evening_is_told(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        $first = User::factory()->ofTheHouse()->create();
        $second = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($first, 'user')->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($second, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->create(['user_id' => null]);

        $this->announce($this->recordingFor($performance));

        Notification::assertNothingSent();
    }

    public function test_the_letter_points_at_the_episode_in_the_library(): void
    {
        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

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
        TechnicalPlan::factory()->approved()->for($performance)->for(User::factory()->ofTheHouse(), 'user')->create();

        $recording = $this->recordingFor($performance);
        $crew = $this->technician();

        $this->announce($recording, $crew);

        $activity = Activity::query()->forSubject($recording)->forEvent('recording_announced')->sole();

        $this->assertTrue($crew->is($activity->causer));
        $this->assertSame(1, $activity->getProperty('recipients'));
    }

    /**
     * The letter carries the summary, so where one is coming, the link alone
     * sends nothing.
     */
    public function test_a_link_waits_for_the_plot_summary_where_one_can_be_written(): void
    {
        $this->summariesCanBeWritten();
        Notification::fake();

        $performance = Performance::factory()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for(User::factory()->ofTheHouse(), 'user')->create();

        $recording = $this->recordingFor($performance);

        $this->announce($recording);

        Notification::assertNothingSent();
        $this->assertNull($recording->fresh()->announced_at);
    }

    public function test_the_first_plot_summary_sends_the_letter(): void
    {
        $this->summariesCanBeWritten();
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

        $recording = $this->recordingFor($performance);
        $this->announce($recording);

        $recording->forceFill([
            'plot_summary' => 'Trupp lendas kosmoselaevaga Saaremaale.',
            'plot_tags' => ['kosmos'],
            'plot_summary_generated_at' => now(),
        ])->save();

        PerformancePlotSummaryWritten::dispatch($recording);

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->recording->is($recording),
        );
        $this->assertNotNull($recording->fresh()->announced_at);

        $activity = Activity::query()->forSubject($recording)->forEvent('recording_announced')->sole();
        $this->assertNull($activity->causer, 'The model wrote the summary; nobody is put down for the letter.');
    }

    /**
     * A fresh summary asked for later, or the crew correcting it, is not news
     * the performers need twice.
     */
    public function test_a_later_summary_sends_no_second_letter(): void
    {
        $this->summariesCanBeWritten();
        Notification::fake();

        $performance = Performance::factory()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for(User::factory()->ofTheHouse(), 'user')->create();

        $recording = PerformanceRecording::factory()->for($performance)->forItem(self::ITEM_ID)->withPlotSummary()->create();

        PerformancePlotSummaryWritten::dispatch($recording);
        PerformancePlotSummaryWritten::dispatch($recording->fresh(), $this->technician());

        Notification::assertCount(1);
    }

    /**
     * Clearing the summary by hand fires the same event, with nothing to show.
     */
    public function test_a_summary_cleared_by_hand_sends_nothing(): void
    {
        $this->summariesCanBeWritten();
        Notification::fake();

        $performance = Performance::factory()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for(User::factory()->ofTheHouse(), 'user')->create();

        $recording = $this->recordingFor($performance);

        PerformancePlotSummaryWritten::dispatch($recording, $this->technician());

        Notification::assertNothingSent();
        $this->assertNull($recording->fresh()->announced_at);
    }

    /**
     * A summary typed into the same save as the link is already there to send.
     */
    public function test_a_link_saved_with_a_hand_written_summary_is_announced_at_once(): void
    {
        $this->summariesCanBeWritten();
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

        $recording = PerformanceRecording::factory()
            ->for($performance)
            ->forItem(self::ITEM_ID)
            ->create(['plot_summary' => 'Tehniku kirjutatud.']);

        $this->announce($recording);

        Notification::assertSentTo($author, PerformanceRecordingAvailable::class);
    }

    /**
     * Subtitles that never came still leave a video worth telling the
     * performers about.
     */
    public function test_a_summary_given_up_on_sends_the_letter_without_one(): void
    {
        $this->summariesCanBeWritten();
        Notification::fake();

        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

        $recording = $this->recordingFor($performance);
        $recording->forceFill(['linked_at' => now()->subDays(15)])->save();

        $this->artisan('performances:summarise-recordings')->assertSuccessful();

        Notification::assertSentTo(
            $author,
            PerformanceRecordingAvailable::class,
            fn (PerformanceRecordingAvailable $notification): bool => $notification->recording->is($recording),
        );
        $this->assertNotNull($recording->fresh()->announced_at);
    }

    public function test_an_abandoned_summary_after_the_letter_sends_no_second_one(): void
    {
        Notification::fake();

        $performance = Performance::factory()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for(User::factory()->ofTheHouse(), 'user')->create();

        $recording = PerformanceRecording::factory()->for($performance)->forItem(self::ITEM_ID)->announced()->create();

        PerformancePlotSummaryAbandoned::dispatch($recording);

        Notification::assertNothingSent();
    }

    public function test_the_letter_carries_the_generated_summary_tags_and_disclaimer(): void
    {
        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();

        $recording = PerformanceRecording::factory()
            ->for($performance)
            ->forItem(self::ITEM_ID)
            ->withPlotSummary('Trupp lendas kosmoselaevaga Saaremaale.', ['kosmos', 'reis'])
            ->create();

        $html = (string) (new PerformanceRecordingAvailable($recording))->toMail($author)->render();

        $this->assertStringContainsString('Sisukokkuvõte', $html);
        $this->assertStringContainsString('Trupp lendas kosmoselaevaga Saaremaale.', $html);
        $this->assertStringContainsString('kosmos', $html);
        $this->assertStringContainsString('reis', $html);
        $this->assertStringContainsString('koostatud tehisaru abil', $html);
    }

    /**
     * The crew's own words are not put down to the model.
     */
    public function test_a_hand_written_summary_is_shown_without_the_disclaimer(): void
    {
        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();

        $recording = PerformanceRecording::factory()
            ->for($performance)
            ->forItem(self::ITEM_ID)
            ->create(['plot_summary' => 'Tehniku kirjutatud.']);

        $html = (string) (new PerformanceRecordingAvailable($recording))->toMail($author)->render();

        $this->assertStringContainsString('Tehniku kirjutatud.', $html);
        $this->assertStringNotContainsString('tehisaru', $html);
    }

    public function test_a_letter_without_a_summary_leaves_the_section_out(): void
    {
        $performance = Performance::factory()->create();
        $author = User::factory()->ofTheHouse()->create();

        $html = (string) (new PerformanceRecordingAvailable($this->recordingFor($performance)))
            ->toMail($author)
            ->render();

        $this->assertStringNotContainsString('Sisukokkuvõte', $html);
        $this->assertStringNotContainsString('tehisaru', $html);
    }

    /**
     * What the suite leaves blank: with a model to read the subtitles, a
     * summary is coming, and the letter waits for it.
     */
    private function summariesCanBeWritten(): void
    {
        config()->set('services.anthropic.key', 'test-key');
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
