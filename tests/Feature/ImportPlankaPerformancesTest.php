<?php

namespace Tests\Feature;

use App\Data\ImportedNight;
use App\Data\ImportedPerformance;
use App\Models\ClaudeReasoningLog;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Services\PlankaPerformanceExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
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
     * One night as the AI would have read it off a card: a show played once, by
     * whoever the show belongs to. The start time defaults to none, which is
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
            showName: $name,
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
     * A night several groups share: one show, an act apiece.
     *
     * @param  list<ImportedPerformance>  $acts
     */
    private function sharedNight(string $name, array $acts, string $date = '2025-10-09'): ImportedNight
    {
        return new ImportedNight(
            showName: $name,
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
            'shows' => [[
                'show_name' => 'Õppelava',
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
            ->expectsOutputToContain('Imported 1 show(s) and 4 performance(s)')
            ->assertSuccessful();

        $performances = Show::query()->sole()->performances()->orderBy('date')->get();

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
            'shows' => [[
                'show_name' => 'Õppelava',
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
            'shows' => [[
                'show_name' => 'Õppelava',
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
            'shows' => [[
                'show_name' => 'Õppelava',
                'date' => '2025-10-09',
                'team_id' => null,
                'performances' => [['title' => 'Märtu10', 'start_time' => '20:00', 'duration_minutes' => 20]],
            ]],
        ])));

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame('card-1', Show::sole()->planka_card_id);
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

        // The show was made by the first card and is not remade by the second,
        // so it goes on pointing at the card it was announced on — while the
        // night the second card added points at that one.
        $this->assertSame('card-1', Show::sole()->planka_card_id);
        $this->assertSame(
            ['card-1', 'card-2'],
            Performance::query()->orderBy('date')->pluck('planka_card_id')->all(),
        );
    }

    public function test_the_reasoning_is_kept_and_tied_to_everything_the_card_made(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'shows' => [[
                'show_name' => 'Õppelava',
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
        $show = Show::sole();

        $this->assertSame([$show->id], $log->shows->pluck('id')->all());
        $this->assertSame(
            Performance::query()->orderBy('id')->pluck('id')->all(),
            $log->performances->pluck('id')->sort()->values()->all(),
        );
        $this->assertSame($log->id, $show->reasoningLog()?->id);
    }

    public function test_a_second_reading_of_the_same_card_explains_only_what_it_added(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $answer = (string) json_encode([
            'shows' => [[
                'show_name' => 'Õppelava',
                'date' => '2025-10-09',
                'team_id' => null,
                'performances' => [['title' => 'Märtu10', 'start_time' => '20:00', 'duration_minutes' => 20]],
            ]],
            'reasoningNotes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'],
        ]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering($answer));

        $this->artisan('planka:import')->assertSuccessful();
        $this->artisan('planka:import')->assertSuccessful();

        // The second run created nothing, so it wrote no account of anything:
        // the show keeps the reading it was made with.
        $this->assertDatabaseCount('claude_reasoning_logs', 1);
        $this->assertDatabaseCount('claude_reasoning_log_subjects', 2);
    }

    public function test_a_card_the_ai_gave_no_account_of_is_imported_without_one(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'shows' => [[
                'show_name' => 'Õppelava',
                'date' => '2025-10-09',
                'performances' => [['title' => 'Märtu10']],
            ]],
        ])));

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertDatabaseCount('claude_reasoning_logs', 0);
        $this->assertNull(Show::sole()->reasoningLog());
    }

    public function test_a_dry_run_keeps_no_reasoning_either(): void
    {
        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);

        $this->app->instance(PlankaPerformanceExtractor::class, $this->extractorAnswering((string) json_encode([
            'shows' => [[
                'show_name' => 'Õppelava',
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

    public function test_an_evening_several_groups_share_is_one_show_played_once(): void
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
            ->expectsOutputToContain('Imported 1 show(s) and 4 performance(s)')
            ->expectsOutputToContain('Creating performance: Õppelava — Märtu10 on 2025-10-09 at 20:00 (performed by: Märtu10)')
            ->assertSuccessful();

        $show = Show::query()->sole();

        $this->assertSame('Õppelava', $show->name);
        // Nobody owns the evening; each act names its own group instead.
        $this->assertNull($show->team_id);

        $performances = $show->performances()->orderBy('date')->get();

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

    public function test_an_act_carries_its_own_group_even_where_the_show_has_one(): void
    {
        $owner = Team::factory()->create(['name' => 'Improteater Ruutu10']);
        $guest = Team::factory()->create(['name' => 'Mätu']);

        Show::factory()->create(['name' => 'Õppelava', 'team_id' => $owner->id]);

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
        // The guest's own group answers for the slot, not the show's owner.
        $this->assertSame('Mätu', $guestSlot->performerName());

        // The act the AI could not place falls back to the show's group.
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
            ->expectsOutputToContain('Imported 0 show(s) and 0 performance(s); 0 show(s) handed to a group, 2 already known')
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
            ->expectsOutputToContain('Imported 0 show(s) and 1 performance(s); 0 show(s) handed to a group, 1 already known')
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
            ->expectsOutputToContain('Imported 1 show(s) and 2 performance(s)')
            ->assertSuccessful();

        $this->assertSame(['Märtu10', 'Improräpp'], Performance::query()->orderBy('date')->pluck('title')->all());
    }

    public function test_a_shared_evening_of_a_deleted_show_is_passed_over_act_by_act(): void
    {
        Show::factory()->create(['name' => 'Õppelava'])->delete();

        $this->fakeBoard([$this->card('card-1', 'Õppelava 9.10')]);
        $this->fakeExtraction([
            $this->sharedNight('Õppelava', [
                $this->act('Märtu10', '20:00', 20),
                $this->act('Improräpp', '20:20', 30),
            ]),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 show(s) and 0 performance(s); 0 show(s) handed to a group, 2 already known')
            ->assertSuccessful();

        $this->assertSame(0, Performance::query()->count());
    }

    public function test_a_lone_act_leaves_the_performance_under_the_shows_own_name(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        // Nothing to tell apart, so nothing is repeated on the performance —
        // which is also how every performance already on the books reads.
        $this->assertNull(Performance::sole()->title);
    }

    public function test_it_creates_a_show_and_a_performance_for_every_night_on_the_card(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Trupp 1'),
            $this->night('JadaJada Special', duration: null),
        ]);

        $this->artisan('planka:import')
            ->assertSuccessful();

        $this->assertSame(2, Show::query()->count());
        $this->assertSame(2, Performance::query()->count());

        $trupp = Show::query()->where('name', 'Trupp 1')->sole();
        $this->assertNull($trupp->team_id);
        $this->assertSame('2025-09-13', $trupp->performances()->sole()->date->toDateString());
        $this->assertSame(90, $trupp->performances()->sole()->duration);

        $jada = Show::query()->where('name', 'JadaJada Special')->sole();
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

    public function test_running_it_again_changes_nothing(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 show(s) and 0 performance(s); 0 show(s) handed to a group, 1 already known')
            ->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
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

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(1, Performance::query()->count());
    }

    public function test_the_same_act_on_a_second_night_is_a_second_performance_of_one_show(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Trupp 1', '2025-09-13'),
            $this->night('Trupp 1', '2025-10-11'),
        ]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(2, Performance::query()->count());
    }

    public function test_it_hangs_new_performances_off_an_existing_show_whatever_its_casing(): void
    {
        $existing = Show::factory()->create(['name' => 'JadaJada Special']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('jadajada special')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
        $this->assertSame($existing->id, Performance::query()->sole()->show_id);
    }

    public function test_it_leaves_a_deleted_show_deleted(): void
    {
        $deleted = Show::factory()->create(['name' => 'Trupp 1']);
        $deleted->delete();

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('the show was deleted here')
            ->assertSuccessful();

        $this->assertSoftDeleted($deleted);
        $this->assertSame(0, Show::query()->count());
        $this->assertSame(0, Performance::query()->count());
    }

    public function test_it_hands_a_new_show_to_the_group_the_ai_matched_it_to(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Creating show: Tšikid reas (owner: Tsikid Reas)')
            ->assertSuccessful();

        $this->assertSame($team->id, Show::query()->sole()->team_id);
    }

    public function test_it_hands_an_ownerless_show_it_already_had_to_a_group(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        $show = Show::factory()->create(['name' => 'Tšikid reas', 'team_id' => null]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Handing over show: Tšikid reas (owner: Tsikid Reas)')
            ->expectsOutputToContain('1 show(s) handed to a group')
            ->assertSuccessful();

        $this->assertSame($team->id, $show->refresh()->team_id);
    }

    public function test_a_show_that_already_has_a_group_keeps_it(): void
    {
        $owner = Team::factory()->create(['name' => 'Tsikid Reas']);
        $other = Team::factory()->create(['name' => 'Jaanuar']);
        $show = Show::factory()->create(['name' => 'Tšikid reas', 'team_id' => $owner->id]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', teamId: $other->id)]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame($owner->id, $show->refresh()->team_id);
    }

    public function test_a_show_the_ai_could_not_place_is_left_ownerless(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1', teamId: null)]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Creating show: Trupp 1')
            ->doesntExpectOutputToContain('owner:')
            ->assertSuccessful();

        $this->assertNull(Show::query()->sole()->team_id);
    }

    public function test_a_hand_over_is_reported_once_however_many_nights_the_show_plays(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        Show::factory()->create(['name' => 'Tšikid reas', 'team_id' => null]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Tšikid reas', '2025-08-14', teamId: $team->id),
            $this->night('Tšikid reas', '2025-09-05', teamId: $team->id),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('1 show(s) handed to a group')
            ->assertSuccessful();
    }

    public function test_a_dry_run_hands_nothing_over(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        $show = Show::factory()->create(['name' => 'Tšikid reas', 'team_id' => null]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import', ['--dry-run' => true])
            ->expectsOutputToContain('Would hand over show: Tšikid reas (owner: Tsikid Reas)')
            ->assertSuccessful();

        $this->assertNull($show->refresh()->team_id);
    }

    public function test_it_creates_a_show_the_house_has_never_had(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Creating show: Trupp 1')
            ->assertSuccessful();

        $show = Show::query()->sole();
        $this->assertSame('Trupp 1', $show->name);
        $this->assertSame('2025-09-13', $show->performances()->sole()->date->toDateString());
    }

    public function test_it_does_not_bring_back_a_deleted_performance(): void
    {
        $show = Show::factory()->create(['name' => 'Trupp 1']);
        Performance::factory()->for($show)->create(['date' => '2025-09-13'])->delete();

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame(0, Performance::query()->count());
    }

    public function test_a_new_show_is_created_once_however_many_nights_it_plays(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Barprov TRT', '2025-09-01'),
            $this->night('Barprov TRT', '2025-10-06'),
            $this->night('Barprov TRT', '2025-11-03'),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 1 show(s) and 3 performance(s)')
            ->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(3, Show::query()->sole()->performances()->count());
    }

    public function test_a_dry_run_reports_a_new_show_once_however_many_nights_it_plays(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Barprov TRT', '2025-09-01'),
            $this->night('Barprov TRT', '2025-10-06'),
        ]);

        // Nothing is written in a dry run, so a second look at the database
        // would report the same show as new all over again.
        $this->artisan('planka:import', ['--dry-run' => true])
            ->expectsOutputToContain('Would import 1 show(s) and 2 performance(s)')
            ->assertSuccessful();
    }

    public function test_names_differing_only_in_estonian_capitals_are_one_show(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('MÄRTU10', '2025-10-09'),
            $this->night('Märtu10', '2025-11-15'),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 1 show(s) and 2 performance(s)')
            ->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
    }

    public function test_a_show_deleted_here_is_passed_over_on_every_night_it_plays(): void
    {
        Show::factory()->create(['name' => 'Tšikid reas'])->delete();

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->night('Tšikid reas', '2025-08-14'),
            $this->night('TŠIKID REAS', '2025-09-05'),
        ]);

        $this->artisan('planka:import')
            ->expectsOutputToContain('Imported 0 show(s) and 0 performance(s); 0 show(s) handed to a group, 2 already known')
            ->assertSuccessful();

        $this->assertSame(0, Show::query()->count());
    }

    public function test_a_show_kept_outlives_an_older_one_of_the_same_name_that_was_deleted(): void
    {
        Show::factory()->create(['name' => 'Tšikid reas'])->delete();
        $kept = Show::factory()->create(['name' => 'Tšikid reas']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Tšikid reas', '2025-08-14')]);

        $this->artisan('planka:import')->assertSuccessful();

        $this->assertSame($kept->id, Performance::query()->sole()->show_id);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->night('Trupp 1')]);

        $this->artisan('planka:import', ['--dry-run' => true])
            ->expectsOutputToContain('Would import 1 show(s) and 1 performance(s)')
            ->assertSuccessful();

        $this->assertSame(0, Show::query()->count());
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
