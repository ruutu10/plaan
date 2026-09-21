<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Enums\TechnicalPlanStatus;
use App\Events\PerformanceRecordingLinked;
use App\Listeners\SyncPerformanceToJellyfin;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Services\JellyfinClient;
use App\Services\JellyfinEpisodeMetadata;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Pushing what this app knows about a night onto the episode that recorded it.
 *
 * Two things are worth more than all the field-by-field checks here. The write
 * endpoint takes the whole item and clears whatever it is not sent, so the push
 * has to read first and keep what it does not own — anything less quietly
 * destroys artwork, run times and everything else the library holds. And only
 * an episode may be written to: a series is a folder, and a tag on a folder is
 * handed down to every one of its children.
 */
class JellyfinSyncTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jellyfin.url', 'https://jellyfin.test');
        config()->set('services.jellyfin.api_key', 'test-key');
        config()->set('services.planka.url', 'https://planka.test');

        Http::preventStrayRequests();
    }

    public function test_the_item_is_read_before_it_is_written(): void
    {
        $this->fakeLibrary();

        $this->push($this->recordingFor($this->night()));

        Http::assertSentInOrder([
            fn (Request $request): bool => $request->method() === 'GET',
            fn (Request $request): bool => $request->method() === 'POST',
        ]);
    }

    /**
     * The one that matters most. A bare read leaves fields out of its answer,
     * and a field left out of the answer is a field the write clears — so the
     * read names them, and everything the library holds beyond them survives.
     */
    public function test_the_push_keeps_what_it_does_not_own(): void
    {
        $this->fakeLibrary([
            'ImageTags' => ['Primary' => 'abc123'],
            'RunTimeTicks' => 54_000_000_000,
            'SeasonId' => 'season-1',
        ]);

        $this->push($this->recordingFor($this->night()));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['ImageTags'] === ['Primary' => 'abc123']
            && $request['RunTimeTicks'] === 54_000_000_000
            && $request['SeasonId'] === 'season-1');

        // And the read asked for the fields a bare one would have left out.
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), 'fields=')
            && str_contains(urldecode($request->url()), 'People')
            && str_contains(urldecode($request->url()), 'ProviderIds'));
    }

    public function test_the_push_carries_the_night_as_this_app_knows_it(): void
    {
        $this->fakeLibrary();

        $performance = $this->night();
        $this->push($this->recordingFor($performance));

        Http::assertSent(function (Request $request) use ($performance): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            return $request['Name'] === $performance->displayName()
                && $request['ForcedSortName'] === $performance->startDate()
                && $request['PremiereDate'] === $performance->startDate().'T00:00:00.0000000Z'
                && $request['ProductionYear'] === $performance->startsAt()->year
                && $request['Studios'] === [['Name' => 'Rühm A']]
                && $request['ProviderIds'] === [
                    'plaan' => (string) $performance->id,
                    'planka' => 'card-1',
                ];
        });
    }

    public function test_the_cast_carries_only_the_roles_jellyfin_has_a_name_for(): void
    {
        $this->fakeLibrary();

        $performance = $this->night();
        $this->staff($performance, 'Mari', PerformanceStaffRole::Performer);
        $this->staff($performance, 'Jaan', PerformanceStaffRole::Technician);
        $this->staff($performance, 'Kati', PerformanceStaffRole::TicketSeller);

        $this->push($this->recordingFor($performance));

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            $names = array_column($request['People'], 'Name');
            $byName = array_column($request['People'], null, 'Name');

            return ! in_array('Kati', $names, true)
                && $byName['Mari']['Type'] === 'Actor'
                && $byName['Mari']['Role'] === 'Esineja'
                && $byName['Jaan']['Type'] === 'Engineer'
                && $byName['Jaan']['Role'] === 'Heli- ja valgusmeister';
        });
    }

    public function test_the_overview_carries_the_links_back_to_plaan_and_planka(): void
    {
        $this->fakeLibrary();

        $performance = $this->night();
        $this->push($this->recordingFor($performance));

        $plaanUrl = route('formats.performances.show', [$performance->format_id, $performance->id]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request['Overview'], $plaanUrl)
            && str_contains($request['Overview'], 'https://planka.test/cards/card-1')
            && str_contains($request['Overview'], 'Rühm A')
            && str_contains($request['Overview'], 'Kultuurikatel'));
    }

    public function test_the_tags_carry_the_troupe_the_venue_the_season_and_the_plan(): void
    {
        $this->fakeLibrary();

        $performance = $this->night();
        $this->planFor($performance, [
            'sound' => ['musicianMode' => 'yes'],
            'equipment' => ['smoke' => 'yes'],
            'scenes' => [['name' => 'Avamine'], ['intermission' => 15]],
        ]);

        $this->push($this->recordingFor($performance));

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            foreach ([
                JellyfinEpisodeMetadata::MARKER_TAG,
                'Improkolmapäev',
                'Rühm A',
                'Kultuurikatel',
                // The night is in October, so it opens the 2026/2027 season.
                '2026/2027',
                'elav muusika',
                'suits',
                'vaheaeg',
            ] as $tag) {
                if (! in_array($tag, $request['Tags'], true)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_a_night_in_spring_belongs_to_the_season_that_opened_before_it(): void
    {
        $this->fakeLibrary();

        $performance = $this->night(['date' => Performance::momentFrom('2027-01-14')]);
        $this->push($this->recordingFor($performance));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && in_array('2026/2027', $request['Tags'], true));
    }

    public function test_a_plan_the_crew_never_got_says_nothing_about_the_night(): void
    {
        $this->fakeLibrary();

        $performance = $this->night();
        $this->planFor($performance, [
            'sound' => ['musicianMode' => 'yes'],
        ], TechnicalPlanStatus::Draft);

        $this->push($this->recordingFor($performance));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && ! in_array('elav muusika', $request['Tags'], true));
    }

    public function test_the_push_is_authorised_with_the_api_key(): void
    {
        $this->fakeLibrary();

        $this->push($this->recordingFor($this->night()));

        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'Authorization',
            'MediaBrowser Token="test-key"',
        ));
    }

    /**
     * A series is a folder, and Jellyfin hands a folder's tags down to every
     * child — so a link pasted from the wrong page would rewrite a format's
     * whole run rather than one evening of it.
     */
    public function test_anything_that_is_not_an_episode_is_refused(): void
    {
        $this->fakeLibrary(['Type' => 'Series']);

        $recording = $this->recordingFor($this->night());

        $this->expectException(RuntimeException::class);

        try {
            $this->push($recording);
        } finally {
            Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

            $this->assertNull($recording->fresh()->synced_at);
        }
    }

    public function test_a_library_that_is_down_records_what_went_wrong(): void
    {
        Http::fake([
            'jellyfin.test/*' => Http::response(['error' => 'nope'], 500),
        ]);

        $recording = $this->recordingFor($this->night());

        try {
            $this->push($recording);
            $this->fail('A failed push should be thrown on so the queue tries again.');
        } catch (\Throwable) {
            // Expected: the retry is the whole point of not swallowing it.
        }

        $recording->refresh();

        $this->assertNull($recording->synced_at);
        $this->assertNotNull($recording->sync_error);
    }

    public function test_a_push_that_gets_through_clears_an_earlier_failure(): void
    {
        $this->fakeLibrary();

        $recording = $this->recordingFor($this->night());
        $recording->forceFill(['sync_error' => 'Connection refused'])->save();

        $this->push($recording);

        $recording->refresh();

        $this->assertNotNull($recording->synced_at);
        $this->assertNull($recording->sync_error);
    }

    public function test_nothing_is_pushed_when_no_library_is_configured(): void
    {
        config()->set('services.jellyfin.url', null);
        config()->set('services.jellyfin.api_key', null);

        Http::fake();

        $this->push($this->recordingFor($this->night()));

        Http::assertNothingSent();
    }

    /**
     * `GET /Items/{id}` looks like the way to fetch one item and only exists
     * from Jellyfin 12; a 10.x server answers it with a bare 400. The item
     * query is what both lines speak, so that is what the read asks.
     */
    public function test_the_item_is_read_through_the_query_both_jellyfin_lines_speak(): void
    {
        $this->fakeLibrary();

        $this->push($this->recordingFor($this->night()));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://jellyfin.test/Items?')
            && str_contains($request->url(), 'ids='.self::ITEM_ID));

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/Items/'.self::ITEM_ID));
    }

    public function test_an_item_the_library_does_not_hold_is_not_written_to(): void
    {
        Http::fake([
            'jellyfin.test/Items?*' => Http::response(['Items' => [], 'TotalRecordCount' => 0]),
        ]);

        $recording = $this->recordingFor($this->night());

        $this->expectException(RuntimeException::class);

        try {
            $this->push($recording);
        } finally {
            Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
        }
    }

    /**
     * PHP spells an empty map and an empty list the same way and JSON does not,
     * so an episode with no provider ids and no artwork — the ordinary state of
     * a freshly scanned recording — would go out with `"ProviderIds": []` and
     * have the whole write refused for not being a dictionary.
     */
    public function test_an_empty_map_is_written_as_a_map_rather_than_a_list(): void
    {
        $this->fakeLibrary([
            'ProviderIds' => [],
            'ImageTags' => [],
            'ImageBlurHashes' => [],
        ]);

        // Nothing of ours to put in it, so what the library had is what goes
        // back — empty, and still a map.
        $performance = $this->night(['planka_card_id' => null]);
        $recording = PerformanceRecording::factory()->for($performance)->forItem(self::ITEM_ID)->create();

        $this->push($recording);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            $body = $request->body();

            return str_contains($body, '"ImageTags":{}')
                && str_contains($body, '"ImageBlurHashes":{}')
                && ! str_contains($body, '"ImageTags":[]');
        });
    }

    public function test_the_push_is_queued_rather_than_done_in_the_request(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new SyncPerformanceToJellyfin(new JellyfinClient, new JellyfinEpisodeMetadata),
        );
    }

    public function test_the_push_is_recorded_in_the_audit_trail(): void
    {
        $this->fakeLibrary();

        $recording = $this->recordingFor($this->night());
        $crew = $this->technician();

        $this->push($recording, $crew);

        $activity = Activity::query()->forSubject($recording)->forEvent('jellyfin_synced')->sole();

        $this->assertTrue($crew->is($activity->causer));
        $this->assertSame(self::ITEM_ID, $activity->getProperty('item_id'));
        $this->assertStringContainsString($crew->name, $activity->description);
    }

    /**
     * Run the push for real, on the sync queue the suite uses, so the listener
     * and the client it calls are both exercised.
     */
    private function push(PerformanceRecording $recording, ?User $linkedBy = null): void
    {
        PerformanceRecordingLinked::dispatch($recording, $linkedBy ?? $this->technician());
    }

    /**
     * A library holding one episode, answering a read with the given fields laid
     * over a plausible one and accepting the write back.
     *
     * @param  array<string, mixed>  $item
     */
    private function fakeLibrary(array $item = []): void
    {
        Http::fake([
            // The read is a query for one id; the write is the item's own
            // route. Ordered so the query is matched before the wildcard.
            'jellyfin.test/Items?*' => Http::response([
                'Items' => [array_replace([
                    'Id' => self::ITEM_ID,
                    'Type' => 'Episode',
                    'Name' => 'Episode 4',
                    'Overview' => 'Whatever the scan made of it.',
                    'People' => [],
                    'Studios' => [],
                    'Tags' => [],
                    'ProviderIds' => [],
                ], $item)],
                'TotalRecordCount' => 1,
            ]),
            'jellyfin.test/Items/*' => Http::response(null, 204),
        ]);
    }

    /**
     * A night with everything worth pushing on it: a format with a description,
     * a group, a venue and a card on the board.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function night(array $attributes = []): Performance
    {
        $team = Team::factory()->create(['name' => 'Rühm A']);

        $format = Format::factory()->for($team, 'team')->create([
            'name' => 'Improkolmapäev',
            'description' => 'Kolm gruppi, üks õhtu.',
        ]);

        return Performance::factory()->for($format)->create(array_replace([
            'date' => Performance::momentFrom('2026-10-07', '19:00'),
            'location' => 'Kultuurikatel',
            'planka_card_id' => 'card-1',
        ], $attributes));
    }

    private function recordingFor(Performance $performance): PerformanceRecording
    {
        return PerformanceRecording::factory()
            ->for($performance)
            ->forItem(self::ITEM_ID)
            ->create();
    }

    private function staff(Performance $performance, string $name, PerformanceStaffRole $role): void
    {
        $performance->staff()->attach(
            User::factory()->create(['name' => $name]),
            ['role' => $role->value],
        );
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function planFor(
        Performance $performance,
        array $content,
        TechnicalPlanStatus $status = TechnicalPlanStatus::Received,
    ): TechnicalPlan {
        return TechnicalPlan::factory()->for($performance)->create(array_replace([
            'status' => $status,
            'sound' => [],
            'scenes' => [],
            'equipment' => [],
            'extra' => [],
        ], $content));
    }
}
