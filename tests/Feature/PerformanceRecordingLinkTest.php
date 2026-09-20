<?php

namespace Tests\Feature;

use App\Events\PerformanceRecordingLinked;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\User;
use App\Services\JellyfinClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Saying where a night's recording is.
 *
 * The link is the crew's to set and nobody else's: it writes to the house's
 * shared media library, over metadata every group's recordings sit beside. What
 * matters here is who may set it, that a link naming no episode is refused, and
 * above all that a save by somebody who may not set it cannot quietly clear the
 * one the crew put there.
 */
class PerformanceRecordingLinkTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private const LINK = 'https://jellyfin.test/web/#/details?id='.self::ITEM_ID.'&serverId=deadbeef';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jellyfin.url', 'https://jellyfin.test');
        config()->set('services.jellyfin.api_key', 'test-key');
    }

    public function test_the_crew_may_say_where_a_recording_is(): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$format, $performance] = $this->performance();

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => self::LINK,
            ])
            ->assertOk()
            ->assertJsonPath('data.recording.url', self::LINK)
            ->assertJsonPath('data.canLinkRecording', true);

        $recording = $performance->fresh()->recording;

        $this->assertSame(self::LINK, $recording->url);
        $this->assertSame(self::ITEM_ID, $recording->item_id);
        $this->assertNull($recording->synced_at);

        Event::assertDispatched(
            PerformanceRecordingLinked::class,
            fn (PerformanceRecordingLinked $event): bool => $event->recording->is($recording),
        );
    }

    /**
     * Jellyfin writes an item's address in more than one shape, and somebody
     * copying an id out of a URL bar hands over the id alone. All of them name
     * the same episode, so all of them are stored as the same episode.
     *
     * @return array<string, array{string}>
     */
    public static function itemAddresses(): array
    {
        return [
            'the current details URL' => ['https://jellyfin.test/web/#/details?id='.self::ITEM_ID],
            'the older index.html URL' => ['https://jellyfin.test/web/index.html#!/details?id='.self::ITEM_ID],
            'an address carrying a server' => [self::LINK],
            'a dashed GUID on its own' => ['a1b2c3d4-e5f6-a7b8-c9d0-e1f2a3b4c5d6'],
            'a bare id on its own' => [self::ITEM_ID],
            'an id spelt in capitals' => ['A1B2C3D4E5F6A7B8C9D0E1F2A3B4C5D6'],
        ];
    }

    #[DataProvider('itemAddresses')]
    public function test_the_episode_is_read_out_of_whatever_was_pasted(string $address): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$format, $performance] = $this->performance();

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => $address,
            ])
            ->assertOk();

        $this->assertSame(self::ITEM_ID, $performance->fresh()->recording->item_id);
    }

    public function test_a_link_that_names_no_episode_is_refused(): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$format, $performance] = $this->performance();

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => 'https://jellyfin.test/web/#/home.html',
            ])
            ->assertJsonValidationErrors('recording_url');

        $this->assertNull($performance->fresh()->recording);

        Event::assertNotDispatched(PerformanceRecordingLinked::class);
    }

    public function test_a_group_member_may_not_say_where_a_recording_is(): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->for($format)->create();

        $this->actingAs($user)
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => self::LINK,
            ])
            ->assertJsonValidationErrors('recording_url');

        $this->assertNull($performance->fresh()->recording);

        Event::assertNotDispatched(PerformanceRecordingLinked::class);
    }

    /**
     * The guard the whole feature turns on. `prohibited` passes a value that is
     * empty, so a form with no business offering the field — and therefore
     * posting it blank — would sail through validation and wipe the link the
     * crew put there. The permission is asked before the link is touched at
     * all, which is what stops that.
     */
    public function test_a_group_member_saving_a_night_leaves_its_recording_alone(): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->for($format)->create();
        PerformanceRecording::factory()->for($performance)->create(['url' => self::LINK]);

        $this->actingAs($user)
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => '',
                'title' => 'Uus nimi',
            ])
            ->assertOk();

        $this->assertSame(self::LINK, $performance->fresh()->recording->url);

        Event::assertNotDispatched(PerformanceRecordingLinked::class);
    }

    public function test_the_error_behind_a_failed_push_is_kept_from_the_group(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->for($format)->create();
        PerformanceRecording::factory()->for($performance)->failed('Connection refused')->create();

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->assertJsonPath('data.recording.syncError', null)
            ->assertJsonPath('data.canLinkRecording', false);

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->assertJsonPath('data.recording.syncError', 'Connection refused');
    }

    public function test_saving_a_night_without_touching_the_link_says_nothing(): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$format, $performance] = $this->performance();
        PerformanceRecording::factory()->for($performance)->synced()->create(['url' => self::LINK]);

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => self::LINK,
            ])
            ->assertOk();

        // The same link saved again is not a new video, so nothing is pushed
        // and nobody is written to — and the earlier push still stands.
        $this->assertNotNull($performance->fresh()->recording->synced_at);

        Event::assertNotDispatched(PerformanceRecordingLinked::class);
    }

    public function test_correcting_the_link_goes_round_again(): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$format, $performance] = $this->performance();
        PerformanceRecording::factory()->for($performance)->synced()->create();

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => self::LINK,
            ])
            ->assertOk();

        $recording = $performance->fresh()->recording;

        $this->assertSame(self::ITEM_ID, $recording->item_id);
        // A link changed is a push not yet made.
        $this->assertNull($recording->synced_at);

        Event::assertDispatched(PerformanceRecordingLinked::class);
    }

    public function test_clearing_the_link_puts_the_recording_aside(): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$format, $performance] = $this->performance();
        $recording = PerformanceRecording::factory()->for($performance)->announced()->create();

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => '',
            ])
            ->assertOk();

        $this->assertNull($performance->fresh()->recording);
        $this->assertSoftDeleted($recording);

        Event::assertNotDispatched(PerformanceRecordingLinked::class);
    }

    /**
     * The reason the row is put aside rather than destroyed: it holds the
     * answer to whether anybody has been written to, and a link cleared by
     * mistake and entered again must not send that letter a second time.
     */
    public function test_a_link_entered_again_finds_the_same_recording(): void
    {
        Event::fake([PerformanceRecordingLinked::class]);

        [$format, $performance] = $this->performance();
        $recording = PerformanceRecording::factory()->for($performance)->announced()->create();
        $recording->delete();

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($format, $performance), [
                'date' => $performance->startDate(),
                'recording_url' => self::LINK,
            ])
            ->assertOk();

        $restored = $performance->fresh()->recording;

        $this->assertTrue($recording->is($restored));
        $this->assertNotNull($restored->announced_at);

        Event::assertDispatched(PerformanceRecordingLinked::class);
    }

    public function test_nothing_is_offered_when_no_library_is_configured(): void
    {
        config()->set('services.jellyfin.url', null);
        config()->set('services.jellyfin.api_key', null);

        [$format, $performance] = $this->performance();

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->assertJsonPath('data.canLinkRecording', false);

        $this->assertFalse(JellyfinClient::isConfigured());
    }

    public function test_a_new_performance_takes_no_recording_link(): void
    {
        [, $format] = $this->formatOfOwnTeam();

        // Even for the crew: a video of an evening nobody has played is not a
        // thing, and there is no performance yet to hang one off.
        $this->actingAs($this->technician())
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-10-01',
                'recording_url' => self::LINK,
            ])
            ->assertJsonValidationErrors('recording_url');

        $this->assertSame(0, PerformanceRecording::query()->count());
    }

    public function test_wiping_a_performance_takes_its_recording_with_it(): void
    {
        [$format, $performance] = $this->performance();
        PerformanceRecording::factory()->for($performance)->create();

        $this->actingAs($this->technician())
            ->deleteJson($this->updateUrl($format, $performance).'?force=1')
            ->assertNoContent();

        $this->assertSame(0, PerformanceRecording::withTrashed()->count());
    }

    /**
     * A performance of a format nobody in particular owns, for the tests about
     * the crew — who reach every night in the house.
     *
     * @return array{Format, Performance}
     */
    private function performance(): array
    {
        $format = Format::factory()->create();

        return [$format, Performance::factory()->for($format)->create()];
    }

    /**
     * A user and a format their own group owns, so they may edit its
     * performances without being one of the crew.
     *
     * @return array{User, Format}
     */
    private function formatOfOwnTeam(): array
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        return [$user, Format::factory()->for($team, 'team')->create()];
    }

    private function updateUrl(Format $format, Performance $performance): string
    {
        return route('api.formats.performances.update', [$format, $performance]);
    }
}
