<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Enums\PerformanceStaffRole;
use App\Events\PerformancePlotSummaryRequested;
use App\Events\PerformancePlotSummaryWritten;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\User;
use App\Services\PerformancePlotSummariser;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * A recording's plot summary, read once off its speech-to-text subtitles.
 *
 * The daily run only lists who is waiting; the listener looks for Estonian
 * text subtitles, hands them to the model, keeps the answer on the recording
 * and pushes it onto the episode's description. See docs/adr/0001.
 */
class PerformancePlotSummaryTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private const SOURCE_ID = 'f0e1d2c3b4a5968778695a4b3c2d1e0f';

    private const SRT = "1\n00:00:01,000 --> 00:00:03,000\nTere tulemast etendusele!\n\n2\n00:00:03,500 --> 00:00:05,000\nTere tulemast etendusele!\n\n3\n00:00:06,000 --> 00:00:09,000\n<i>Meie lugu algab</i>\nkosmoselaeval.\n";

    /**
     * The bodies sent to the model, in order.
     *
     * @var list<array<string, mixed>>
     */
    private array $sentBodies = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jellyfin.url', 'https://jellyfin.test');
        config()->set('services.jellyfin.api_key', 'test-key');
        config()->set('services.anthropic.key', 'test-key');

        Http::preventStrayRequests();
    }

    public function test_the_daily_run_asks_after_recordings_still_waiting(): void
    {
        Event::fake([PerformancePlotSummaryRequested::class]);

        $waiting = $this->recording();
        PerformanceRecording::factory()->withPlotSummary()->create();
        PerformanceRecording::factory()->plotSummaryAbandoned()->create();
        // Written once and cleared by hand: the crew's call, not asked again.
        PerformanceRecording::factory()->create(['plot_summary_generated_at' => now()]);
        PerformanceRecording::factory()->create()->delete();
        PerformanceRecording::factory()->create()->performance->delete();

        $this->artisan('performances:summarise-recordings')->assertSuccessful();

        Event::assertDispatchedTimes(PerformancePlotSummaryRequested::class, 1);
        Event::assertDispatched(
            PerformancePlotSummaryRequested::class,
            fn (PerformancePlotSummaryRequested $event): bool => $event->recording->is($waiting)
                && $event->requestedBy === null,
        );
    }

    public function test_the_daily_run_gives_up_on_a_recording_left_untranscribed_for_thirty_days(): void
    {
        Event::fake([PerformancePlotSummaryRequested::class]);

        $this->travel(-31)->days();
        $stale = $this->recording();
        $this->travelBack();

        $this->artisan('performances:summarise-recordings')->assertSuccessful();

        Event::assertNotDispatched(PerformancePlotSummaryRequested::class);
        $this->assertNotNull($stale->fresh()->plot_summary_abandoned_at);
        $this->assertTrue(Activity::query()->where('event', 'plot_summary_abandoned')->whereMorphedTo('subject', $stale)->exists());
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        Event::fake([PerformancePlotSummaryRequested::class]);

        $this->travel(-31)->days();
        $stale = $this->recording();
        $this->travelBack();
        $this->recording();

        $this->artisan('performances:summarise-recordings', ['--dry-run' => true])->assertSuccessful();

        Event::assertNotDispatched(PerformancePlotSummaryRequested::class);
        $this->assertNull($stale->fresh()->plot_summary_abandoned_at);
    }

    public function test_the_daily_run_does_nothing_without_claude(): void
    {
        Event::fake([PerformancePlotSummaryRequested::class]);
        config()->set('services.anthropic.key', '');

        $this->recording();

        $this->artisan('performances:summarise-recordings')->assertSuccessful();

        Event::assertNotDispatched(PerformancePlotSummaryRequested::class);
    }

    public function test_a_recording_with_no_subtitles_yet_is_left_waiting(): void
    {
        $this->fakeLibrary(streams: [
            ['Type' => 'Video', 'Index' => 0],
            ['Type' => 'Audio', 'Index' => 1, 'Language' => 'est'],
        ]);
        $this->bindModelAnswering($this->usable('Ei tohiks küsida.'));

        $recording = $this->recording();

        PerformancePlotSummaryRequested::dispatch($recording);

        $this->assertTrue($recording->fresh()->awaitsPlotSummary());
        $this->assertSame([], $this->sentBodies);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/Videos/'));
    }

    public function test_the_estonian_text_subtitles_are_read_and_summarised(): void
    {
        $this->fakeLibrary(streams: [
            ['Type' => 'Subtitle', 'Index' => 2, 'Language' => 'est', 'IsTextSubtitleStream' => false, 'Codec' => 'PGSSUB'],
            ['Type' => 'Subtitle', 'Index' => 3, 'Language' => 'eng', 'IsTextSubtitleStream' => true],
            ['Type' => 'Subtitle', 'Index' => 4, 'Language' => 'est', 'IsTextSubtitleStream' => true],
        ]);
        $this->bindModelAnswering($this->usable('Trupp lendas kosmoselaevaga Saaremaale.'));

        $recording = $this->recording();
        $recording->performance->staff()->attach(
            User::factory()->create(['name' => 'Mari Maasikas']),
            ['role' => PerformanceStaffRole::Performer->value],
        );

        PerformancePlotSummaryRequested::dispatch($recording);

        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://jellyfin.test/Videos/'.self::ITEM_ID.'/'.self::SOURCE_ID.'/Subtitles/4/Stream.srt');

        $recording->refresh();
        $this->assertSame('Trupp lendas kosmoselaevaga Saaremaale.', $recording->plot_summary);
        $this->assertNotNull($recording->plot_summary_generated_at);
        $this->assertTrue(Activity::query()->where('event', 'plot_summary_generated')->whereMorphedTo('subject', $recording)->exists());

        // The model was given the dialogue without timestamps or repeats, and
        // who was on stage to put misheard names right.
        $prompt = $this->sentBodies[0]['messages'][0]['content'];
        $this->assertStringContainsString("Tere tulemast etendusele!\nMeie lugu algab kosmoselaeval.", $prompt);
        $this->assertStringNotContainsString('-->', $prompt);
        $this->assertStringContainsString('Mari Maasikas', $prompt);

        // And the episode's description now carries it.
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains((string) $request['Overview'], "Sisukokkuvõte:\nTrupp lendas kosmoselaevaga Saaremaale."));
    }

    public function test_unreadable_subtitles_are_given_up_on(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering((string) json_encode(['usable' => false, 'summary' => null, 'reason' => 'Ainult muusika.']));

        $recording = $this->recording();

        PerformancePlotSummaryRequested::dispatch($recording);

        $recording->refresh();
        $this->assertNull($recording->plot_summary);
        $this->assertNotNull($recording->plot_summary_abandoned_at);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_a_library_that_is_down_is_retried_rather_than_given_up_on(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));
        $this->bindModelAnswering($this->usable('Ei jõua siia.'));

        $recording = $this->recording();

        try {
            PerformancePlotSummaryRequested::dispatch($recording);
            $this->fail('The failure should have been thrown on for the queue to retry.');
        } catch (ConnectionException) {
            //
        }

        $this->assertTrue($recording->fresh()->awaitsPlotSummary());
    }

    public function test_the_crew_may_ask_for_a_fresh_summary(): void
    {
        Event::fake([PerformancePlotSummaryRequested::class]);

        $recording = PerformanceRecording::factory()->withPlotSummary('Vana.')->create();
        $technician = $this->technician();

        $this->actingAs($technician)
            ->postJson($this->regenerateUrl($recording->performance))
            ->assertAccepted();

        $recording->refresh();
        $this->assertNull($recording->plot_summary_generated_at);
        $this->assertSame('Vana.', $recording->plot_summary);
        Event::assertDispatched(
            PerformancePlotSummaryRequested::class,
            fn (PerformancePlotSummaryRequested $event): bool => $event->recording->is($recording)
                && $event->requestedBy?->is($technician) === true,
        );
    }

    public function test_a_fresh_summary_replaces_the_old_one(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering($this->usable('Uus kokkuvõte.'));

        $recording = PerformanceRecording::factory()->forItem(self::ITEM_ID)->withPlotSummary('Vana.')->create();

        $this->actingAs($this->technician())
            ->postJson($this->regenerateUrl($recording->performance))
            ->assertAccepted();

        $this->assertSame('Uus kokkuvõte.', $recording->fresh()->plot_summary);
    }

    public function test_only_the_crew_may_ask_for_a_fresh_summary(): void
    {
        $recording = PerformanceRecording::factory()->withPlotSummary()->create();

        $this->actingAs(User::factory()->create())
            ->postJson($this->regenerateUrl($recording->performance))
            ->assertForbidden();
    }

    public function test_a_night_with_no_recording_has_nothing_to_summarise(): void
    {
        $performance = Performance::factory()->create();

        $this->actingAs($this->technician())
            ->postJson($this->regenerateUrl($performance))
            ->assertForbidden();
    }

    public function test_the_crew_may_correct_the_summary_and_it_is_pushed(): void
    {
        Event::fake([PerformancePlotSummaryWritten::class]);

        $recording = PerformanceRecording::factory()->withPlotSummary('Vana.')->create();
        $performance = $recording->performance;

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($performance), [
                'date' => $performance->startDate(),
                'recording_plot_summary' => '  Parandatud.  ',
            ])
            ->assertOk()
            ->assertJsonPath('data.recording.plotSummary', 'Parandatud.');

        Event::assertDispatched(PerformancePlotSummaryWritten::class);
        $this->assertTrue(Activity::query()->where('event', 'plot_summary_edited')->whereMorphedTo('subject', $recording)->exists());
    }

    public function test_an_unchanged_summary_is_not_pushed_again(): void
    {
        Event::fake([PerformancePlotSummaryWritten::class]);

        $recording = PerformanceRecording::factory()->withPlotSummary('Sama.')->create();
        $performance = $recording->performance;

        $this->actingAs($this->technician())
            ->patchJson($this->updateUrl($performance), [
                'date' => $performance->startDate(),
                'recording_plot_summary' => 'Sama.',
            ])
            ->assertOk();

        Event::assertNotDispatched(PerformancePlotSummaryWritten::class);
    }

    public function test_only_the_crew_may_correct_the_summary(): void
    {
        $user = User::factory()->create();
        $format = Format::factory()->for($this->teamOf($user), 'team')->create();
        $performance = Performance::factory()->for($format)->create();
        PerformanceRecording::factory()->for($performance)->withPlotSummary('Vana.')->create();

        $this->actingAs($user)
            ->patchJson($this->updateUrl($performance), [
                'date' => $performance->startDate(),
                'recording_plot_summary' => 'Minu oma.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recording_plot_summary');
    }

    public function test_another_link_is_another_video_and_starts_its_own_wait(): void
    {
        $recording = PerformanceRecording::factory()->withPlotSummary()->create();

        $recording->update(['url' => 'https://jellyfin.test/web/#/details?id='.self::ITEM_ID]);

        $this->assertTrue($recording->fresh()->awaitsPlotSummary());
    }

    public function test_a_link_cleared_and_restored_keeps_its_summary(): void
    {
        $recording = PerformanceRecording::factory()->withPlotSummary('Jääb alles.')->create();

        $recording->delete();
        $recording->restore();

        $this->assertSame('Jääb alles.', $recording->fresh()->plot_summary);
    }

    public function test_everybody_who_can_open_the_night_sees_the_summary(): void
    {
        $user = User::factory()->create();
        $format = Format::factory()->for($this->teamOf($user), 'team')->create();
        $performance = Performance::factory()->for($format)->create();
        PerformanceRecording::factory()->for($performance)->withPlotSummary('Kõigile näha.')->create();

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->assertJsonPath('data.recording.plotSummary', 'Kõigile näha.')
            ->assertJsonPath('data.recording.plotSummaryState', 'written')
            ->assertJsonPath('data.canRegeneratePlotSummary', false);
    }

    public function test_the_transcript_keeps_the_words_and_drops_the_timing(): void
    {
        $this->assertSame(
            "Tere tulemast etendusele!\nMeie lugu algab kosmoselaeval.",
            PerformancePlotSummariser::transcriptFrom("\xEF\xBB\xBF".str_replace("\n", "\r\n", self::SRT)),
        );
    }

    public function test_the_summary_is_kept_as_plain_paragraphs(): void
    {
        $this->assertSame(
            "Esimene lõik.\n\nTeine lõik.",
            PerformancePlotSummariser::tidy("  Esimene\u{3000}lõik.\u{3000}\r\n\r\n\n\n  Teine\u{00A0}lõik.  "),
        );
    }

    /**
     * A recording on an ordinary night, waiting on its summary.
     */
    private function recording(): PerformanceRecording
    {
        return PerformanceRecording::factory()->forItem(self::ITEM_ID)->create();
    }

    /**
     * A library holding one episode with the given streams, serving their
     * subtitles as {@see SRT} and accepting the push back.
     *
     * @param  list<array<string, mixed>>|null  $streams
     */
    private function fakeLibrary(?array $streams = null): void
    {
        $streams ??= [['Type' => 'Subtitle', 'Index' => 2, 'Language' => 'est', 'IsTextSubtitleStream' => true]];

        Http::fake([
            'jellyfin.test/Items?*' => Http::response([
                'Items' => [[
                    'Id' => self::ITEM_ID,
                    'Type' => 'Episode',
                    'Name' => 'Episode 4',
                    'MediaSources' => [['Id' => self::SOURCE_ID, 'MediaStreams' => $streams]],
                ]],
                'TotalRecordCount' => 1,
            ]),
            'jellyfin.test/Videos/*' => Http::response(self::SRT),
            'jellyfin.test/Items/*' => Http::response(null, 204),
        ]);
    }

    /**
     * Have the summariser the listener is handed talk to a transport that
     * always answers with the given text.
     */
    private function bindModelAnswering(string $text): void
    {
        $sentBodies = &$this->sentBodies;

        $transporter = new class($text, $sentBodies) implements ClientInterface
        {
            /**
             * @param  list<array<string, mixed>>  $sentBodies
             */
            public function __construct(private string $text, private array &$sentBodies)
            {
                //
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->sentBodies[] = json_decode((string) $request->getBody(), true) ?: [];

                return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                    'id' => 'msg_test',
                    'type' => 'message',
                    'role' => 'assistant',
                    'model' => 'claude-sonnet-5',
                    'content' => [['type' => 'text', 'text' => $this->text]],
                    'stop_reason' => 'end_turn',
                    'stop_sequence' => null,
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                ]));
            }
        };

        $this->app->instance(PerformancePlotSummariser::class, new PerformancePlotSummariser(
            new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transporter]),
        ));
    }

    private function usable(string $summary): string
    {
        return (string) json_encode(['usable' => true, 'summary' => $summary, 'reason' => null]);
    }

    private function regenerateUrl(Performance $performance): string
    {
        return route('api.formats.performances.plot-summary.store', [$performance->format_id, $performance]);
    }

    private function updateUrl(Performance $performance): string
    {
        return route('api.formats.performances.update', [$performance->format_id, $performance]);
    }
}
