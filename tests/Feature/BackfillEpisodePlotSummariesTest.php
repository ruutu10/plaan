<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Services\PerformancePlotSummariser;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * Plot summaries written straight into Jellyfin for episodes this app holds no
 * record of: library in, library out, nothing in the database.
 */
class BackfillEpisodePlotSummariesTest extends TestCase
{
    private const SERIES_ID = '00000000000000000000000000000001';

    private const BARE = 'a0000000000000000000000000000001';

    private const DESCRIBED = 'a0000000000000000000000000000002';

    private const UNTRANSCRIBED = 'a0000000000000000000000000000003';

    private const SOURCE_ID = 'f0e1d2c3b4a5968778695a4b3c2d1e0f';

    private const SRT = "1\n00:00:01,000 --> 00:00:03,000\nMeie lugu algab kosmoselaeval.\n";

    /** @var list<array<string, mixed>> */
    private array $sentBodies = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jellyfin.url', 'https://jellyfin.test');
        config()->set('services.jellyfin.api_key', 'test-key');
        config()->set('services.anthropic.key', 'test-key');

        Http::preventStrayRequests();
    }

    public function test_it_summarises_only_bare_transcribed_episodes_and_writes_them_back(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering($this->usable('Laev lendab kosmosesse.', ['kosmos', 'Laul']));
        Log::spy();

        $this->artisan('performances:summarise-backfill')
            ->expectsOutputToContain('Improteater')
            ->expectsOutputToContain('Episood 1 — summarising')
            ->expectsOutputToContain('written, tags: kosmos, laul')
            ->expectsOutputToContain('│ Laev lendab kosmosesse.')
            ->assertSuccessful();

        $this->assertCount(1, $this->sentBodies);
        $prompt = $this->sentBodies[0]['messages'][0]['content'];
        $this->assertStringContainsString('Formaat: Improteater', $prompt);
        $this->assertStringContainsString('Kuupäev: 2024-03-01', $prompt);
        $this->assertStringContainsString('- Mari Maasikas', $prompt);
        $this->assertStringContainsString('Meie lugu algab kosmoselaeval.', $prompt);

        $writes = Http::recorded(fn (Request $request): bool => $request->method() === 'POST');
        $this->assertCount(1, $writes);

        [$write] = $writes->first();
        $this->assertSame('https://jellyfin.test/Items/'.self::BARE, $write->url());
        $this->assertSame('Laev lendab kosmosesse.', $write['Overview']);
        $this->assertSame(['olemasolev', 'kosmos', 'laul'], $write['Tags']);
        // A person's empty nested map goes back as a map, or Jellyfin refuses the write.
        $this->assertStringContainsString('"ImageBlurHashes":{"Primary":{}}', $write->body());
        Log::shouldHaveReceived('debug')->withArgs(
            fn (string $message, array $context = []): bool => $message === 'Plot summary content'
                && $context['item_id'] === self::BARE
                && $context['summary'] === 'Laev lendab kosmosesse.'
                && $context['tags'] === ['kosmos', 'laul'],
        );

        // The rest of the item travels back untouched.
        $this->assertSame('Episood 1', $write['Name']);
    }

    public function test_one_episode_by_id_is_summarised_over_its_description_and_tags(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering($this->usable('Uus kokkuvõte.', ['kosmos']));

        $this->artisan('performances:summarise-backfill', ['--id' => self::DESCRIBED])
            ->expectsOutputToContain('Episood 2 — summarising')
            ->assertSuccessful();

        $this->assertCount(1, $this->sentBodies);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'includeItemTypes=Series'));

        $writes = Http::recorded(fn (Request $request): bool => $request->method() === 'POST');
        $this->assertCount(1, $writes);

        [$write] = $writes->first();
        $this->assertSame('https://jellyfin.test/Items/'.self::DESCRIBED, $write->url());
        $this->assertSame('Uus kokkuvõte.', $write['Overview']);
        $this->assertSame(['kosmos'], $write['Tags']);
    }

    public function test_a_dry_run_by_id_writes_nothing(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering($this->usable('Ei tohiks juhtuda.'));

        $this->artisan('performances:summarise-backfill', ['--id' => self::DESCRIBED, '--dry-run' => true])
            ->expectsOutputToContain('Episood 2 — would summarise')
            ->assertSuccessful();

        $this->assertSame([], $this->sentBodies);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_an_episode_by_id_without_subtitles_fails(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering($this->usable('Ei tohiks juhtuda.'));

        $this->artisan('performances:summarise-backfill', ['--id' => self::UNTRANSCRIBED])
            ->expectsOutputToContain('no Estonian subtitles')
            ->assertFailed();

        $this->assertSame([], $this->sentBodies);
    }

    public function test_an_unknown_id_fails(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering($this->usable('Ei tohiks juhtuda.'));

        $this->artisan('performances:summarise-backfill', ['--id' => 'ffffffffffffffffffffffffffffffff'])
            ->expectsOutputToContain('Jellyfin holds no episode ffffffffffffffffffffffffffffffff.')
            ->assertFailed();

        $this->assertSame([], $this->sentBodies);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_a_dry_run_asks_nothing_and_writes_nothing(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering($this->usable('Ei tohiks juhtuda.'));

        $this->artisan('performances:summarise-backfill', ['--dry-run' => true])
            ->expectsOutputToContain('Episood 1 — would summarise')
            ->assertSuccessful();

        $this->assertSame([], $this->sentBodies);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/Videos/'));
    }

    public function test_an_unreadable_transcript_is_reported_and_not_written(): void
    {
        $this->fakeLibrary();
        $this->bindModelAnswering((string) json_encode([
            'usable' => false, 'summary' => null, 'tags' => [], 'reason' => 'Ainult muusika.',
        ]));

        $this->artisan('performances:summarise-backfill')
            ->expectsOutputToContain('unreadable: Ainult muusika.')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_a_failing_episode_is_reported_and_the_run_fails(): void
    {
        $this->fakeLibrary(subtitleStatus: 500);
        $this->bindModelAnswering($this->usable('Ei jõua siia.'));

        Log::spy();

        $this->artisan('performances:summarise-backfill')
            ->expectsOutputToContain('failed: '.trim(self::SRT))
            ->assertFailed();

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        // The whole answer, not the exception message's truncated preview of it.
        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context): bool => $context['item_id'] === self::BARE
                && $context['status'] === 500
                && $context['response_body'] === self::SRT,
        );
    }

    public function test_the_command_is_scheduled_to_run_weekly(): void
    {
        // Bootstraps the console kernel, which is what loads the schedule.
        $this->artisan('schedule:list')
            ->expectsOutputToContain('performances:summarise-backfill')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'performances:summarise-backfill'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * 0', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_it_does_nothing_without_configuration(): void
    {
        config()->set('services.anthropic.key', null);

        $this->artisan('performances:summarise-backfill')
            ->expectsOutputToContain('not configured')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    /**
     * One series of three episodes: one bare and transcribed, one already
     * described, one with no subtitles.
     */
    private function fakeLibrary(int $subtitleStatus = 200): void
    {
        $subtitles = [['Type' => 'Subtitle', 'Index' => 2, 'Language' => 'est', 'IsTextSubtitleStream' => true]];

        $bare = [
            'Id' => self::BARE,
            'Type' => 'Episode',
            'Name' => 'Episood 1',
            'SeriesName' => 'Improteater',
            'PremiereDate' => '2024-03-01T00:00:00.0000000Z',
            'Overview' => null,
            'Tags' => ['olemasolev'],
            'People' => [['Name' => 'Mari Maasikas', 'Type' => 'Actor', 'ImageBlurHashes' => ['Primary' => []]]],
            'MediaSources' => [['Id' => self::SOURCE_ID, 'MediaStreams' => $subtitles]],
        ];

        $episodes = [
            $bare,
            [...$bare, 'Id' => self::DESCRIBED, 'Name' => 'Episood 2', 'Overview' => 'Juba kirjas.'],
            [...$bare, 'Id' => self::UNTRANSCRIBED, 'Name' => 'Episood 3', 'MediaSources' => [['Id' => self::SOURCE_ID, 'MediaStreams' => []]]],
        ];

        Http::fake(function (Request $request) use ($episodes, $subtitleStatus) {
            $url = $request->url();

            if (str_contains($url, '/Videos/')) {
                return Http::response(self::SRT, $subtitleStatus);
            }

            if ($request->method() === 'POST') {
                return Http::response(null, 204);
            }

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if (($query['includeItemTypes'] ?? null) === 'Series') {
                return Http::response(['Items' => [['Id' => self::SERIES_ID, 'Name' => 'Improteater', 'Type' => 'Series']], 'TotalRecordCount' => 1]);
            }

            if (($query['parentId'] ?? null) === self::SERIES_ID) {
                return Http::response(['Items' => $episodes, 'TotalRecordCount' => count($episodes)]);
            }

            $byId = array_values(array_filter($episodes, fn (array $episode): bool => $episode['Id'] === ($query['ids'] ?? null)));

            if ($byId !== []) {
                return Http::response(['Items' => $byId, 'TotalRecordCount' => 1]);
            }

            return Http::response(['Items' => [], 'TotalRecordCount' => 0]);
        });
    }

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

    /**
     * @param  list<string>  $tags
     */
    private function usable(string $summary, array $tags = []): string
    {
        return (string) json_encode(['usable' => true, 'summary' => $summary, 'tags' => $tags, 'reason' => null]);
    }
}
