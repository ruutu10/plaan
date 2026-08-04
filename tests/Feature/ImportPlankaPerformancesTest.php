<?php

namespace Tests\Feature;

use App\Console\Commands\ImportPlankaPerformances;
use App\Data\ImportedNight;
use App\Data\ImportedPerformance;
use App\Data\ImportSummary;
use App\Enums\CreatedBy;
use App\Models\ClaudeReasoningLog;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Services\PlankaPerformanceExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use ReflectionMethod;
use RuntimeException;
use Tests\Concerns\AnswersAsTheExtractionModel;
use Tests\TestCase;

class ImportPlankaPerformancesTest extends TestCase
{
    use AnswersAsTheExtractionModel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.planka.url', 'https://planka.test');
        config()->set('services.planka.list_ids', 'list-1');
        config()->set('services.planka.token', 'test-token');
    }

    /**
     * Fake one watched list, which Planka serves with its cards beside it.
     *
     * @param  list<array<string, mixed>>  $cards
     */
    private function fakeBoard(array $cards): void
    {
        $this->fakeLists(['list-1' => $cards]);
    }

    /**
     * Fake several watched lists at once, keyed by list id. Planka serves a
     * list with its cards and their label joins, and the board with the label
     * names those joins point at.
     *
     * @param  array<string, list<array<string, mixed>>>  $lists
     */
    private function fakeLists(array $lists): void
    {
        Http::preventStrayRequests();

        $stubs = [
            'planka.test/api/boards/board-1' => Http::response([
                'item' => ['id' => 'board-1'],
                'included' => [
                    'labels' => [
                        ['id' => 'label-etendus', 'name' => 'ETENDUS'],
                        ['id' => 'label-tootuba', 'name' => 'TÖÖTUBA'],
                    ],
                ],
            ]),
        ];

        foreach ($lists as $listId => $cards) {
            $cardLabels = [];

            foreach ($cards as $card) {
                foreach ($card['labelIds'] ?? [] as $labelId) {
                    $cardLabels[] = ['cardId' => $card['id'], 'labelId' => $labelId];
                }
            }

            $stubs["planka.test/api/lists/{$listId}"] = Http::response([
                'item' => ['id' => $listId, 'name' => "List {$listId}", 'boardId' => 'board-1'],
                'included' => ['cards' => $cards, 'cardLabels' => $cardLabels],
            ]);
        }

        Http::fake($stubs);
    }

    /**
     * One card as Planka serves it.
     *
     * @param  list<string>  $labelIds
     * @return array<string, mixed>
     */
    private function card(string $id = 'card-1', string $name = '13.09 õhtu', array $labelIds = []): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'description' => 'Toimumise kuupäev: 13.09.2025',
            'dueDate' => '2025-09-13T15:00:00.000Z',
            'labelIds' => $labelIds,
        ];
    }

    /**
     * Make the AI hand back exactly these nights for every card it is given.
     *
     * @param  list<ImportedNight>  $nights
     */
    private function fakeExtraction(array $nights): void
    {
        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) use ($nights) {
            $mock->shouldReceive('extract')->andReturn($nights);
            $mock->shouldReceive('reasoningNotes')->andReturn([]);
        });
    }

    /**
     * One night as the AI would have read it off a card: a format played once, by
     * whoever the format belongs to. The start time defaults to none, which is
     * the common case — most cards name a date and leave the hour to the house.
     */
    private function night(
        string $name,
        string $date = '2025-09-13',
        ?int $duration = 90,
        ?int $teamId = null,
        ?string $startTime = null,
    ): ImportedNight {
        return new ImportedNight(
            formatName: $name,
            date: Carbon::parse($date),
            teamId: $teamId,
            performances: [
                new ImportedPerformance(
                    startTime: $startTime,
                    duration: $duration,
                    teamId: $teamId,
                ),
            ],
        );
    }

    /**
     * A night several groups share: one format, an act apiece.
     *
     * @param  list<ImportedPerformance>  $acts
     */
    private function sharedNight(string $name, array $acts, string $date = '2025-10-09'): ImportedNight
    {
        return new ImportedNight(
            formatName: $name,
            date: Carbon::parse($date),
            teamId: null,
            performances: $acts,
        );
    }

    /**
     * One act on such a night.
     */
    private function act(
        string $title,
        ?string $startTime = null,
        ?int $duration = null,
        ?int $teamId = null,
    ): ImportedPerformance {
        return new ImportedPerformance(
            title: $title,
            startTime: $startTime,
            duration: $duration,
            teamId: $teamId,
        );
    }

    public function test_an_imported_performance_keeps_the_hour_the_card_named(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1', startTime: '18:00')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame('18:00', Performance::sole()->startTime());
    }

    public function test_a_card_naming_no_hour_leaves_the_performance_at_the_houses_usual_one(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        $performance = Performance::sole();

        $this->assertSame('19:00', $performance->startTime());
        // Still the night the card announced, not the day the UTC lands on.
        $this->assertSame('2025-09-13', $performance->startDate());
    }

    public function test_a_night_already_imported_is_not_imported_again_when_the_card_gains_an_hour(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        // The board is tidied up and the card now says when the act is on. It
        // is the same night, so it stays one performance.
        $this->fakeExtraction([$this->night('Trupp 1', startTime: '21:45')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(1, Performance::query()->count());
        $this->assertSame('19:00', Performance::sole()->startTime());
    }

    public function test_a_real_card_travels_from_the_model_answer_to_the_books(): void
    {
        // The one test that runs the extractor and the importer together, so
        // the shape one hands over is the shape the other reads. Everything is
        // real but the model's own answer.
        $marturu = Team::factory()->create(['name' => 'Märtu10']);
        $matu = Team::factory()->create(['name' => 'Mätu']);

        $this->fakeBoard([[
            'id' => 'card-1',
            'name' => 'Õppelava 9.10',
            'description' => "- **Toimumise kuupäev:** 9.10.2025\n- **Etteaste algus:** 20:00\n"
                ."- Esinejad: Märtu10 (20min), Tõnis ilma Tanelita külalisega (30min), Mätu (30min), Improräpp (30min)\n"
                .'- Heli- ja valgus: Tom',
            'dueDate' => '2025-10-09T15:00:00.000Z',
            'labelIds' => [],
        ]]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'team_id' => null,
                'performances' => [
                    ['title' => 'Märtu10', 'start_time' => '20:00', 'duration_minutes' => 20, 'team_id' => $marturu->id],
                    ['title' => 'Tõnis ilma Tanelita külalisega', 'start_time' => '20:20', 'duration_minutes' => 30, 'team_id' => null],
                    ['title' => 'Mätu', 'start_time' => '20:50', 'duration_minutes' => 30, 'team_id' => $matu->id],
                    ['title' => 'Improräpp', 'start_time' => '21:20', 'duration_minutes' => 30, 'team_id' => null],
                ],
            ]],
        ])));

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 1 format(s) and 4 performance(s)')
            ->assertSuccessful();

        $performances = Format::query()->sole()->performances()->orderBy('date')->get();

        $this->assertSame(
            ['Märtu10 20:00', 'Tõnis ilma Tanelita külalisega 20:20', 'Mätu 20:50', 'Improräpp 21:20'],
            $performances->map(fn (Performance $p): string => "{$p->title} {$p->startTime()}")->all(),
        );
        $this->assertSame(['Märtu10', null, 'Mätu', null], $performances->map(
            fn (Performance $p): ?string => $p->team?->name,
        )->all());
        // The crew is not on the bill.
        $this->assertSame(0, Performance::query()->where('title', 'like', '%Tom%')->count());
    }

    public function test_the_run_says_how_the_ai_read_each_card(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'team_id' => null,
                'performances' => [['title' => 'Märtu10', 'start_time' => '20:00', 'duration_minutes' => 20]],
            ]],
            'reasoningNotes' => [
                'Kuupäev real "Toimumise kuupäev: 9.10.2025".',
                'Tom on heli ja valgus, seega meeskond, mitte esineja.',
            ],
        ])));

        $this->artisan('planka:import')
            ->expectsOutputToContain('Read "Õppelava 9.10" as follows:')
            ->expectsOutputToContain('- Kuupäev real "Toimumise kuupäev: 9.10.2025".')
            ->expectsOutputToContain('- Tom on heli ja valgus, seega meeskond, mitte esineja.')
            ->assertSuccessful();
    }

    public function test_a_card_the_ai_gave_no_account_of_says_nothing_extra(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'performances' => [['title' => 'Märtu10']],
            ]],
        ])));

        $this->artisan('planka:import')
            ->doesntExpectOutputToContain('as follows:')
            ->assertSuccessful();
    }

    public function test_every_record_says_which_card_it_was_read_off(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'team_id' => null,
                'performances' => [['title' => 'Märtu10', 'start_time' => '20:00', 'duration_minutes' => 20]],
            ]],
        ])));

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame('card-1', Performance::sole()->planka_card_id);
    }

    public function test_a_night_added_by_a_second_card_says_that_card(): void
    {
        $this->fakeBoard([
            $this->card('card-1', 'Õppelava 9.10'),
            $this->card('card-2', 'Õppelava 16.10'),
        ]);

        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn(
                [$this->night('Õppelava', '2025-10-09')],
                [$this->night('Õppelava', '2025-10-16')],
            );
            $mock->shouldReceive('reasoningNotes')->andReturn([]);
        });

        $this->artisan('planka:import')->assertSuccessful();

        // Each night points at the card that announced it, even though both
        // nights belong to the one format the first card made.
        $this->assertSame(
            ['card-1', 'card-2'],
            Performance::query()->orderBy('date')->pluck('planka_card_id')->all(),
        );
    }

    public function test_the_reasoning_is_kept_and_tied_to_everything_the_card_made(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'team_id' => null,
                'performances' => [
                    ['title' => 'Märtu10', 'start_time' => '20:00', 'duration_minutes' => 20],
                    ['title' => 'Mätu', 'start_time' => '20:20', 'duration_minutes' => 30],
                ],
            ]],
            'reasoningNotes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'],
        ])));

        $this->artisan('planka:import')->assertSuccessful();

        // One reading, however much the card turned out to describe.
        $this->assertDatabaseCount('claude_reasoning_logs', 1);

        $log = ClaudeReasoningLog::sole();

        $this->assertSame('card-1', $log->card_id);
        $this->assertSame('Õppelava 9.10', $log->card_name);
        $this->assertSame(['Kuupäev real "Toimumise kuupäev: 9.10.2025".'], $log->notes);

        // And everything that reading made can be traced back to it.
        $format = Format::sole();

        $this->assertSame([$format->id], $log->formats->pluck('id')->all());
        $this->assertSame(
            Performance::query()->orderBy('id')->pluck('id')->all(),
            $log->performances->pluck('id')->sort()->values()->all(),
        );
        $this->assertSame([$log->id], $format->reasoningLogs->pluck('id')->all());
    }

    public function test_a_format_built_card_by_card_keeps_every_cards_reasoning(): void
    {
        // The Õppelava case: one format, a night from each of two cards. The format
        // is created by the first and only added to by the second, and both
        // readings have to survive — the one that explains a wrong date is
        // rarely the one the format was created with.
        $this->fakeBoard([
            $this->card('card-1', 'Õppelava 9.10'),
            $this->card('card-2', 'TLN õppelava 15.11'),
        ]);

        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn(
                [$this->night('Õppelava', '2025-10-09')],
                [$this->night('Õppelava', '2025-11-15')],
            );
            $mock->shouldReceive('reasoningNotes')->andReturn(
                ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'],
                ['Kuupäev real "Toimumise kuupäev: 15.11.2025".'],
            );
        });

        $this->artisan('planka:import')->assertSuccessful();

        $format = Format::sole();

        $this->assertDatabaseCount('claude_reasoning_logs', 2);
        $this->assertSame(
            ['Õppelava 9.10', 'TLN õppelava 15.11'],
            $format->reasoningLogs()->orderBy('claude_reasoning_logs.id')->pluck('card_name')->all(),
        );

        // Each night still explains only itself.
        $this->assertSame(
            ['Õppelava 9.10', 'TLN õppelava 15.11'],
            Performance::query()
                ->orderBy('date')
                ->get()
                ->map(fn (Performance $performance): ?string => $performance->reasoningLogs->first()?->card_name)
                ->all(),
        );
    }

    public function test_a_second_reading_of_the_same_card_explains_only_what_it_added(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $answer = (string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'team_id' => null,
                'performances' => [['title' => 'Märtu10', 'start_time' => '20:00', 'duration_minutes' => 20]],
            ]],
            'reasoningNotes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'],
        ]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering($answer));

        $this->artisan('planka:import')->assertSuccessful();
        $this->artisan('planka:import')->assertSuccessful();

        // The second run created nothing, so it wrote no account of anything.
        // This is what keeps a format from gathering another reading every week
        // now that a card which adds a night explains the format as well.
        $this->assertDatabaseCount('claude_reasoning_logs', 1);
        $this->assertDatabaseCount('claude_reasoning_log_subjects', 2);
    }

    public function test_a_card_the_ai_gave_no_account_of_is_imported_without_one(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'performances' => [['title' => 'Märtu10']],
            ]],
        ])));

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertDatabaseCount('claude_reasoning_logs', 0);
        $this->assertCount(0, Format::sole()->reasoningLogs);
    }

    public function test_a_dry_run_keeps_no_reasoning_either(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'performances' => [['title' => 'Märtu10']],
            ]],
            'reasoningNotes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'],
        ])));

        $this->artisan('planka:import', ['--dry-run' => true])
            ->expectsOutputToContain('Read "Õppelava 9.10" as follows:')
            ->assertSuccessful();

        $this->assertDatabaseCount('claude_reasoning_logs', 0);
    }

    public function test_an_evening_several_groups_share_is_one_format_played_once(): void
    {
        $marturu = Team::factory()->create(['name' => 'Märtu10']);
        $matu = Team::factory()->create(['name' => 'Mätu']);

        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);
        $this->fakeExtraction([
            $this->sharedNight('Õppelava', [
                $this->act('Märtu10', '20:00', 20, $marturu->id),
                $this->act('Tõnis ilma Tanelita külalisega', '20:20', 30),
                $this->act('Mätu', '20:50', 30, $matu->id),
                $this->act('Improräpp', '21:20', 30),
            ]),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 1 format(s) and 4 performance(s)')
            ->expectsOutputToContain('Creating performance: Õppelava — Märtu10 on 2025-10-09 at 20:00 (performed by: Märtu10)')
            ->assertSuccessful();

        $format = Format::query()->sole();

        $this->assertSame('Õppelava', $format->name);
        // Nobody owns the evening; each act names its own group instead.
        $this->assertNull($format->team_id);

        $performances = $format->performances()->orderBy('date')->get();

        $this->assertCount(4, $performances);
        $this->assertSame(
            ['Märtu10', 'Tõnis ilma Tanelita külalisega', 'Mätu', 'Improräpp'],
            $performances->pluck('title')->all(),
        );
        $this->assertSame(
            ['20:00', '20:20', '20:50', '21:20'],
            $performances->map(fn (Performance $performance): string => $performance->startTime())->all(),
        );
        $this->assertSame([20, 30, 30, 30], $performances->pluck('duration')->all());
        $this->assertSame(
            [$marturu->id, null, $matu->id, null],
            $performances->pluck('team_id')->all(),
        );
    }

    public function test_an_act_carries_its_own_group_even_where_the_format_has_one(): void
    {
        $owner = Team::factory()->create(['name' => 'Improteater Ruutu10']);
        $guest = Team::factory()->create(['name' => 'Mätu']);

        Format::factory()->create(['name' => 'Õppelava', 'team_id' => $owner->id]);

        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);
        $this->fakeExtraction([
            $this->sharedNight('Õppelava', [
                $this->act('Märtu10', '20:00', 20),
                $this->act('Mätu', '20:20', 30, $guest->id),
            ]),
        ]);

        $this->artisan('planka:import')->assertSuccessful();

        $guestSlot = Performance::query()->where('title', 'Mätu')->sole();

        $this->assertSame($guest->id, $guestSlot->team_id);
        // The guest's own group answers for the slot, not the format's owner.
        $this->assertSame('Mätu', $guestSlot->performerName());

        // The act the AI could not place falls back to the format's group.
        $this->assertSame('Improteater Ruutu10', Performance::query()->where('title', 'Märtu10')->sole()->performerName());
    }

    public function test_reimporting_a_shared_evening_changes_nothing(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);
        $this->fakeExtraction([
            $this->sharedNight('Õppelava', [
                $this->act('Märtu10', '20:00', 20),
                $this->act('Mätu', '20:20', 30),
            ]),
        ]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 format(s) and 0 performance(s); 0 format(s) handed to a group, 2 already known')
            ->assertSuccessful();

        $this->assertSame(2, Performance::query()->count());
    }

    public function test_an_act_added_to_the_card_later_joins_the_night_alone(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        // The bill grows between the two runs. Artisan hands out the same
        // command instance twice, so both readings are queued on one mock.
        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->once()->andReturn([
                $this->sharedNight('Õppelava', [$this->act('Märtu10', '20:00', 20)]),
            ]);
            $mock->shouldReceive('extract')->once()->andReturn([
                $this->sharedNight('Õppelava', [
                    $this->act('Märtu10', '20:00', 20),
                    $this->act('Improräpp', '20:20', 30),
                ]),
            ]);
            $mock->shouldReceive('reasoningNotes')->andReturn([]);
        });

        $this->artisan('planka:import')->assertSuccessful();

        // The act already on the books is left as it is; only the new one is
        // registered.
        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 format(s) and 1 performance(s); 0 format(s) handed to a group, 1 already known')
            ->assertSuccessful();

        $this->assertSame(
            ['Märtu10', 'Improräpp'],
            Performance::query()->orderBy('date')->pluck('title')->all(),
        );
    }

    public function test_the_same_act_listed_twice_on_one_night_is_one_performance(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);
        $this->fakeExtraction([
            $this->sharedNight('Õppelava', [
                $this->act('Märtu10', '20:00', 20),
                $this->act('MÄRTU10', '20:20', 30),
                $this->act('Improräpp', '20:50', 30),
            ]),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 1 format(s) and 2 performance(s)')
            ->assertSuccessful();

        $this->assertSame(['Märtu10', 'Improräpp'], Performance::query()->orderBy('date')->pluck('title')->all());
    }

    public function test_two_untitled_acts_on_one_shared_night_are_kept_apart(): void
    {
        // Neither act has a name to be matched by, so a database check keyed on
        // title alone must not treat the second as a duplicate of the first.
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);
        $this->fakeExtraction([
            new ImportedNight(
                formatName: 'Õppelava',
                date: Carbon::parse('2025-10-09'),
                performances: [
                    new ImportedPerformance(startTime: '20:00', duration: 20),
                    new ImportedPerformance(startTime: '20:30', duration: 20),
                ],
            ),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 1 format(s) and 2 performance(s)')
            ->assertSuccessful();

        $this->assertSame(2, Performance::query()->count());
    }

    public function test_a_titled_act_a_card_had_already_put_on_the_books_is_not_registered_again(): void
    {
        // The exact case a second Planka card can produce: the act is already a
        // row in the database, by format, date and title, before this card is
        // ever read.
        $format = Format::factory()->create(['name' => 'Õppelava']);
        Performance::factory()->for($format)->create([
            'date' => Performance::momentFrom('2025-10-09', '20:00'),
            'title' => 'Märtu10',
        ]);

        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);
        $this->fakeExtraction([
            $this->sharedNight('Õppelava', [$this->act('Märtu10', '20:00', 20)]),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 format(s) and 0 performance(s); 0 format(s) handed to a group, 1 already known')
            ->assertSuccessful();

        $this->assertSame(1, Performance::query()->count());
    }

    public function test_the_final_database_check_catching_a_duplicate_logs_a_warning(): void
    {
        // importNight()'s own batched check already catches the previous
        // test's case before importPerformance() is ever reached, so the only
        // way to see the final check actually catch something is to call it
        // directly — which is also how a second run overlapping this one
        // would reach it, past the batched check, with the competing row
        // already on the books.
        Log::spy();

        $format = Format::factory()->create(['name' => 'Õppelava']);
        Performance::factory()->for($format)->create([
            'date' => Performance::momentFrom('2025-10-09', '20:00'),
            'title' => 'Märtu10',
        ]);

        $command = $this->app->make(ImportPlankaPerformances::class);
        $importPerformance = new ReflectionMethod($command, 'importPerformance');
        $importPerformance->setAccessible(true);

        $night = $this->sharedNight('Õppelava', [$this->act('Märtu10', '20:00', 20)]);
        $summary = new ImportSummary;

        $importPerformance->invoke($command, $format, $night, $night->performances[0], $summary, false);

        $this->assertSame(1, $summary->skipped);
        $this->assertSame(1, Performance::query()->count());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message
                === 'Skipped a performance already on the books, caught only by the final database check'
                && $context['format_id'] === $format->id
                && $context['title'] === 'Märtu10'
                && $context['date'] === '2025-10-09')
            ->once();
    }

    public function test_a_shared_evening_of_a_deleted_format_is_passed_over_act_by_act(): void
    {
        Format::factory()->create(['name' => 'Õppelava'])->delete();

        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);
        $this->fakeExtraction([
            $this->sharedNight('Õppelava', [
                $this->act('Märtu10', '20:00', 20),
                $this->act('Improräpp', '20:20', 30),
            ]),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 format(s) and 0 performance(s); 0 format(s) handed to a group, 2 already known')
            ->assertSuccessful();

        $this->assertSame(0, Performance::query()->count());
    }

    public function test_a_lone_act_leaves_the_performance_under_the_formats_own_name(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        // Nothing to tell apart, so nothing is repeated on the performance —
        // which is also how every performance already on the books reads.
        $this->assertNull(Performance::sole()->title);
    }

    public function test_it_creates_a_format_and_a_performance_for_every_night_on_the_card(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Trupp 1'),
            $this->night('JadaJada Special', duration: null),
        ]);

        $this->artisan('planka:import')
            ->assertSuccessful();

        $this->assertSame(2, Format::query()->count());
        $this->assertSame(2, Performance::query()->count());

        $trupp = Format::query()->where('name', 'Trupp 1')->sole();
        $this->assertNull($trupp->team_id);
        $this->assertSame('2025-09-13', $trupp->performances()->sole()->date->toDateString());
        $this->assertSame(90, $trupp->performances()->sole()->duration);

        $jada = Format::query()->where('name', 'JadaJada Special')->sole();
        $this->assertNull($jada->performances()->sole()->duration);
    }

    public function test_an_imported_performance_waits_to_be_reviewed(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')
            ->assertSuccessful();

        // A card is a claim about a night, so the performance it announces is a
        // draft until an admin has vouched for it — and until then it is not
        // among the performances a technical plan can be written for.
        $this->assertTrue(Performance::sole()->is_draft);
        $this->assertSame(0, Performance::query()->vouchedFor()->count());
    }

    public function test_what_the_import_creates_says_it_came_from_the_board(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')
            ->assertSuccessful();

        // Both the format the house had never had and the night it was made for:
        // nobody typed either, and the screens have to be able to say so.
        $this->assertSame(CreatedBy::PlankaImport, Format::sole()->created_by);
        $this->assertSame(CreatedBy::PlankaImport, Performance::sole()->created_by);
    }

    public function test_a_night_added_to_a_format_somebody_entered_leaves_the_format_alone(): void
    {
        $format = Format::factory()->create(['name' => 'Trupp 1']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')
            ->assertSuccessful();

        // The card announced the night, not the format: a format somebody entered
        // by hand does not become an imported one by being played again.
        $this->assertSame(CreatedBy::Manual, $format->fresh()->created_by);
        $this->assertSame(CreatedBy::PlankaImport, Performance::sole()->created_by);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 format(s) and 0 performance(s); 0 format(s) handed to a group, 1 already known')
            ->assertSuccessful();

        $this->assertSame(1, Format::query()->count());
        $this->assertSame(1, Performance::query()->count());
    }

    public function test_the_same_act_named_twice_on_one_night_is_one_performance(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Trupp 1'),
            $this->night('trupp 1'),
        ]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(1, Format::query()->count());
        $this->assertSame(1, Performance::query()->count());
    }

    public function test_the_same_act_on_a_second_night_is_a_second_performance_of_one_format(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Trupp 1', '2025-09-13'),
            $this->night('Trupp 1', '2025-10-11'),
        ]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(1, Format::query()->count());
        $this->assertSame(2, Performance::query()->count());
    }

    public function test_it_hangs_new_performances_off_an_existing_format_whatever_its_casing(): void
    {
        $existing = Format::factory()->create(['name' => 'JadaJada Special']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('jadajada special')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(1, Format::query()->count());
        $this->assertSame($existing->id, Performance::query()->sole()->format_id);
    }

    public function test_it_leaves_a_deleted_format_deleted(): void
    {
        $deleted = Format::factory()->create(['name' => 'Trupp 1']);
        $deleted->delete();

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('the format was deleted here')
            ->assertSuccessful();

        $this->assertSoftDeleted($deleted);
        $this->assertSame(0, Format::query()->count());
        $this->assertSame(0, Performance::query()->count());
    }

    public function test_it_hands_a_new_format_to_the_group_the_ai_matched_it_to(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Creating format: Tšikid reas (owner: Tsikid Reas)')
            ->assertSuccessful();

        $this->assertSame($team->id, Format::query()->sole()->team_id);
    }

    public function test_it_hands_an_ownerless_format_it_already_had_to_a_group(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        $format = Format::factory()->create(['name' => 'Tšikid reas', 'team_id' => null]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Handing over format: Tšikid reas (owner: Tsikid Reas)')
            ->expectsOutputToContain('1 format(s) handed to a group')
            ->assertSuccessful();

        $this->assertSame($team->id, $format->refresh()->team_id);
    }

    public function test_a_format_that_already_has_a_group_keeps_it(): void
    {
        $owner = Team::factory()->create(['name' => 'Tsikid Reas']);
        $other = Team::factory()->create(['name' => 'Jaanuar']);
        $format = Format::factory()->create(['name' => 'Tšikid reas', 'team_id' => $owner->id]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', teamId: $other->id)]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame($owner->id, $format->refresh()->team_id);
    }

    public function test_a_format_the_ai_could_not_place_is_left_ownerless(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1', teamId: null)]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Creating format: Trupp 1')
            ->doesntExpectOutputToContain('owner:')
            ->assertSuccessful();

        $this->assertNull(Format::query()->sole()->team_id);
    }

    public function test_a_hand_over_is_reported_once_however_many_nights_the_format_plays(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        Format::factory()->create(['name' => 'Tšikid reas', 'team_id' => null]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Tšikid reas', '2025-08-14', teamId: $team->id),
            $this->night('Tšikid reas', '2025-09-05', teamId: $team->id),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('1 format(s) handed to a group')
            ->assertSuccessful();
    }

    public function test_a_dry_run_hands_nothing_over(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        $format = Format::factory()->create(['name' => 'Tšikid reas', 'team_id' => null]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import', ['--dry-run' => true])
            ->expectsOutputToContain('Would hand over format: Tšikid reas (owner: Tsikid Reas)')
            ->assertSuccessful();

        $this->assertNull($format->refresh()->team_id);
    }

    public function test_it_creates_a_format_the_house_has_never_had(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Creating format: Trupp 1')
            ->assertSuccessful();

        $format = Format::query()->sole();
        $this->assertSame('Trupp 1', $format->name);
        $this->assertSame('2025-09-13', $format->performances()->sole()->date->toDateString());
    }

    public function test_it_does_not_bring_back_a_deleted_performance(): void
    {
        $format = Format::factory()->create(['name' => 'Trupp 1']);
        Performance::factory()->for($format)->create(['date' => '2025-09-13'])->delete();

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(0, Performance::query()->count());
    }

    public function test_a_new_format_is_created_once_however_many_nights_it_plays(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Barprov TRT', '2025-09-01'),
            $this->night('Barprov TRT', '2025-10-06'),
            $this->night('Barprov TRT', '2025-11-03'),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 1 format(s) and 3 performance(s)')
            ->assertSuccessful();

        $this->assertSame(1, Format::query()->count());
        $this->assertSame(3, Format::query()->sole()->performances()->count());
    }

    public function test_a_dry_run_reports_a_new_format_once_however_many_nights_it_plays(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Barprov TRT', '2025-09-01'),
            $this->night('Barprov TRT', '2025-10-06'),
        ]);

        // Nothing is written in a dry run, so a second look at the database
        // would report the same format as new all over again.
        $this->artisan('planka:import', ['--dry-run' => true])
            ->expectsOutputToContain('Would import 1 format(s) and 2 performance(s)')
            ->assertSuccessful();
    }

    public function test_names_differing_only_in_estonian_capitals_are_one_format(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('MÄRTU10', '2025-10-09'),
            $this->night('Märtu10', '2025-11-15'),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 1 format(s) and 2 performance(s)')
            ->assertSuccessful();

        $this->assertSame(1, Format::query()->count());
    }

    public function test_a_format_deleted_here_is_passed_over_on_every_night_it_plays(): void
    {
        Format::factory()->create(['name' => 'Tšikid reas'])->delete();

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Tšikid reas', '2025-08-14'),
            $this->night('TŠIKID REAS', '2025-09-05'),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 format(s) and 0 performance(s); 0 format(s) handed to a group, 2 already known')
            ->assertSuccessful();

        $this->assertSame(0, Format::query()->count());
    }

    public function test_a_format_kept_outlives_an_older_one_of_the_same_name_that_was_deleted(): void
    {
        Format::factory()->create(['name' => 'Tšikid reas'])->delete();
        $kept = Format::factory()->create(['name' => 'Tšikid reas']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', '2025-08-14')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame($kept->id, Performance::query()->sole()->format_id);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import', ['--dry-run' => true])
            ->expectsOutputToContain('Would import 1 format(s) and 1 performance(s)')
            ->assertSuccessful();

        $this->assertSame(0, Format::query()->count());
        $this->assertSame(0, Performance::query()->count());
    }

    public function test_a_card_the_ai_cannot_read_does_not_cost_us_the_others(): void
    {
        $this->fakeBoard([$this->card('card-1'), $this->card('card-2', '11.10 õhtu')]);

        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->once()->andThrow(new RuntimeException('AI is down'));
            $mock->shouldReceive('extract')->once()->andReturn([$this->night('Trupp 1')]);
            $mock->shouldReceive('reasoningNotes')->andReturn([]);
        });

        $this->artisan('planka:import')
            ->expectsOutputToContain('AI is down')
            ->assertSuccessful();

        $this->assertSame(1, Performance::query()->count());
    }

    public function test_it_passes_over_cards_labelled_as_a_workshop(): void
    {
        $this->fakeBoard([
            $this->card('card-1', 'Töötuba', labelIds: ['label-tootuba']),
            $this->card('card-2', '13.09 õhtu', labelIds: ['label-etendus']),
        ]);

        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')
                ->once()
                ->with('13.09 õhtu', \Mockery::any(), \Mockery::any(), ['ETENDUS'])
                ->andReturn([$this->night('Trupp 1')]);
            $mock->shouldReceive('reasoningNotes')->andReturn([]);
        });

        $this->artisan('planka:import')
            ->expectsOutputToContain('Passing over "Töötuba": labelled TÖÖTUBA.')
            ->expectsOutputToContain('1 card(s) passed over by label')
            ->assertSuccessful();

        $this->assertSame(1, Performance::query()->count());
    }

    public function test_the_excluded_labels_are_configurable_and_matched_whatever_the_casing(): void
    {
        config()->set('services.planka.excluded_labels', ['etendus']);

        $this->fakeBoard([$this->card('card-1', '13.09 õhtu', labelIds: ['label-etendus'])]);
        $this->mock(
            PlankaPerformanceExtractor::class,
            fn (MockInterface $mock) => $mock->shouldNotReceive('extract'),
        );

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(0, Performance::query()->count());
    }

    public function test_a_card_carrying_no_label_is_read(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(1, Performance::query()->count());
    }

    public function test_it_asks_each_board_for_its_labels_once(): void
    {
        config()->set('services.planka.list_ids', 'list-1,list-2');

        $this->fakeLists(['list-1' => [$this->card()], 'list-2' => []]);
        $this->fakeExtraction([]);

        $this->artisan('planka:import')->assertSuccessful();

        Http::assertSentCount(3);
    }

    public function test_cards_without_a_description_are_left_alone(): void
    {
        $this->fakeBoard([['id' => 'card-1', 'name' => 'Tühi', 'description' => null, 'dueDate' => null]]);
        $this->mock(
            PlankaPerformanceExtractor::class,
            fn (MockInterface $mock) => $mock->shouldNotReceive('extract'),
        );

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(0, Performance::query()->count());
    }

    public function test_it_reads_every_watched_list(): void
    {
        config()->set('services.planka.list_ids', 'list-1, list-2 ,list-3');

        $this->fakeLists([
            'list-1' => [$this->card('card-1')],
            'list-2' => [],
            // The same card can sit in two lists; it must be read once.
            'list-3' => [$this->card('card-2', '11.10 õhtu'), $this->card('card-1')],
        ]);

        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->once()->andReturn([$this->night('Trupp 1', '2025-09-13')]);
            $mock->shouldReceive('extract')->once()->andReturn([$this->night('Trupp 2', '2025-10-11')]);
            $mock->shouldReceive('reasoningNotes')->andReturn([]);
        });

        $this->artisan('planka:import')
            ->expectsOutputToContain('Read 2 card(s) from 3 Planka list(s).')
            ->assertSuccessful();

        $this->assertSame(2, Performance::query()->count());

        foreach (['list-1', 'list-2', 'list-3'] as $listId) {
            Http::assertSent(fn ($request) => $request->url() === "https://planka.test/api/lists/{$listId}");
        }
    }

    public function test_it_hands_the_card_to_the_ai_whole(): void
    {
        $this->fakeBoard([$this->card(labelIds: ['label-etendus'])]);

        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')
                ->once()
                // The board's labels included: they are the producers' own word
                // for what kind of evening the card is describing.
                ->with('13.09 õhtu', 'Toimumise kuupäev: 13.09.2025', '2025-09-13T15:00:00.000Z', ['ETENDUS'])
                ->andReturn([]);
            $mock->shouldReceive('reasoningNotes')->andReturn([]);
        });

        $this->artisan('planka:import')->assertSuccessful();
    }

    public function test_it_signs_its_requests_with_the_api_key_header(): void
    {
        $this->fakeBoard([]);
        $this->fakeExtraction([]);

        $this->artisan('planka:import')->assertSuccessful();

        // Planka refuses the same value on an Authorization header.
        Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-token')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_it_refuses_to_run_unconfigured(): void
    {
        config()->set('services.planka.list_ids', '');

        $this->artisan('planka:import')
            ->expectsOutputToContain('Planka is not configured')
            ->assertFailed();
    }

    public function test_it_reports_a_board_that_will_not_answer(): void
    {
        Http::fake(['planka.test/*' => Http::response(['message' => 'nope'], 500)]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Could not read the Planka lists')
            ->assertFailed();
    }
}
