<?php

namespace Tests\Feature;

use App\Data\ImportedPerformance;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Services\PlankaPerformanceExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ImportPlankaPerformancesTest extends TestCase
{
    use RefreshDatabase;

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
     * Make the AI hand back exactly these stagings for every card it is given.
     *
     * @param  list<ImportedPerformance>  $performances
     */
    private function fakeExtraction(array $performances): void
    {
        $this->mock(
            PlankaPerformanceExtractor::class,
            fn (MockInterface $mock) => $mock->shouldReceive('extract')->andReturn($performances),
        );
    }

    private function staging(string $name, string $date = '2025-09-13', ?int $duration = 90, ?int $teamId = null): ImportedPerformance
    {
        return new ImportedPerformance($name, Carbon::parse($date), $duration, $teamId);
    }

    public function test_it_creates_a_show_and_a_performance_for_every_act_on_the_card(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->staging('Trupp 1'),
            $this->staging('JadaJada Special', duration: null),
        ]);

        $this->artisan('planka:import-performances')
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

    public function test_running_it_again_changes_nothing(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Trupp 1')]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('Imported 0 show(s) and 0 performance(s); 0 show(s) handed to a group, 1 already known')
            ->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(1, Performance::query()->count());
    }

    public function test_the_same_act_named_twice_on_one_night_is_one_performance(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->staging('Trupp 1'),
            $this->staging('trupp 1'),
        ]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(1, Performance::query()->count());
    }

    public function test_the_same_act_on_a_second_night_is_a_second_performance_of_one_show(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->staging('Trupp 1', '2025-09-13'),
            $this->staging('Trupp 1', '2025-10-11'),
        ]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(2, Performance::query()->count());
    }

    public function test_it_hangs_new_performances_off_an_existing_show_whatever_its_casing(): void
    {
        $existing = Show::factory()->create(['name' => 'JadaJada Special']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('jadajada special')]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
        $this->assertSame($existing->id, Performance::query()->sole()->show_id);
    }

    public function test_it_leaves_a_deleted_show_deleted(): void
    {
        $deleted = Show::factory()->create(['name' => 'Trupp 1']);
        $deleted->delete();

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Trupp 1')]);

        $this->artisan('planka:import-performances')
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
        $this->fakeExtraction([$this->staging('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('Creating show: Tšikid reas (owner: Tsikid Reas)')
            ->assertSuccessful();

        $this->assertSame($team->id, Show::query()->sole()->team_id);
    }

    public function test_it_hands_an_ownerless_show_it_already_had_to_a_group(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        $show = Show::factory()->create(['name' => 'Tšikid reas', 'team_id' => null]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import-performances')
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
        $this->fakeExtraction([$this->staging('Tšikid reas', teamId: $other->id)]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->assertSame($owner->id, $show->refresh()->team_id);
    }

    public function test_a_show_the_ai_could_not_place_is_left_ownerless(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Trupp 1', teamId: null)]);

        $this->artisan('planka:import-performances')
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
            $this->staging('Tšikid reas', '2025-08-14', teamId: $team->id),
            $this->staging('Tšikid reas', '2025-09-05', teamId: $team->id),
        ]);

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('1 show(s) handed to a group')
            ->assertSuccessful();
    }

    public function test_a_dry_run_hands_nothing_over(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        $show = Show::factory()->create(['name' => 'Tšikid reas', 'team_id' => null]);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Tšikid reas', teamId: $team->id)]);

        $this->artisan('planka:import-performances', ['--dry-run' => true])
            ->expectsOutputToContain('Would hand over show: Tšikid reas (owner: Tsikid Reas)')
            ->assertSuccessful();

        $this->assertNull($show->refresh()->team_id);
    }

    public function test_it_creates_a_show_the_house_has_never_had(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Trupp 1')]);

        $this->artisan('planka:import-performances')
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
        $this->fakeExtraction([$this->staging('Trupp 1')]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->assertSame(0, Performance::query()->count());
    }

    public function test_a_new_show_is_created_once_however_many_nights_it_plays(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->staging('Barprov TRT', '2025-09-01'),
            $this->staging('Barprov TRT', '2025-10-06'),
            $this->staging('Barprov TRT', '2025-11-03'),
        ]);

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('Imported 1 show(s) and 3 performance(s)')
            ->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(3, Show::query()->sole()->performances()->count());
    }

    public function test_a_dry_run_reports_a_new_show_once_however_many_nights_it_plays(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->staging('Barprov TRT', '2025-09-01'),
            $this->staging('Barprov TRT', '2025-10-06'),
        ]);

        // Nothing is written in a dry run, so a second look at the database
        // would report the same show as new all over again.
        $this->artisan('planka:import-performances', ['--dry-run' => true])
            ->expectsOutputToContain('Would import 1 show(s) and 2 performance(s)')
            ->assertSuccessful();
    }

    public function test_names_differing_only_in_estonian_capitals_are_one_show(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->staging('MÄRTU10', '2025-10-09'),
            $this->staging('Märtu10', '2025-11-15'),
        ]);

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('Imported 1 show(s) and 2 performance(s)')
            ->assertSuccessful();

        $this->assertSame(1, Show::query()->count());
    }

    public function test_a_show_deleted_here_is_passed_over_on_every_night_it_plays(): void
    {
        Show::factory()->create(['name' => 'Tšikid reas'])->delete();

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([
            $this->staging('Tšikid reas', '2025-08-14'),
            $this->staging('TŠIKID REAS', '2025-09-05'),
        ]);

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('Imported 0 show(s) and 0 performance(s); 0 show(s) handed to a group, 2 already known')
            ->assertSuccessful();

        $this->assertSame(0, Show::query()->count());
    }

    public function test_a_show_kept_outlives_an_older_one_of_the_same_name_that_was_deleted(): void
    {
        Show::factory()->create(['name' => 'Tšikid reas'])->delete();
        $kept = Show::factory()->create(['name' => 'Tšikid reas']);

        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Tšikid reas', '2025-08-14')]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->assertSame($kept->id, Performance::query()->sole()->show_id);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Trupp 1')]);

        $this->artisan('planka:import-performances', ['--dry-run' => true])
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
            $mock->shouldReceive('extract')->once()->andReturn([$this->staging('Trupp 1')]);
        });

        $this->artisan('planka:import-performances')
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

        $this->mock(PlankaPerformanceExtractor::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('extract')
            ->once()
            ->with('13.09 õhtu', \Mockery::any(), \Mockery::any())
            ->andReturn([$this->staging('Trupp 1')]));

        $this->artisan('planka:import-performances')
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

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->assertSame(0, Performance::query()->count());
    }

    public function test_a_card_carrying_no_label_is_read(): void
    {
        $this->fakeBoard([$this->card()]);
        $this->fakeExtraction([$this->staging('Trupp 1')]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        $this->assertSame(1, Performance::query()->count());
    }

    public function test_it_asks_each_board_for_its_labels_once(): void
    {
        config()->set('services.planka.list_ids', 'list-1,list-2');

        $this->fakeLists(['list-1' => [$this->card()], 'list-2' => []]);
        $this->fakeExtraction([]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        Http::assertSentCount(3);
    }

    public function test_cards_without_a_description_are_left_alone(): void
    {
        $this->fakeBoard([['id' => 'card-1', 'name' => 'Tühi', 'description' => null, 'dueDate' => null]]);
        $this->mock(
            PlankaPerformanceExtractor::class,
            fn (MockInterface $mock) => $mock->shouldNotReceive('extract'),
        );

        $this->artisan('planka:import-performances')->assertSuccessful();

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
            $mock->shouldReceive('extract')->once()->andReturn([$this->staging('Trupp 1', '2025-09-13')]);
            $mock->shouldReceive('extract')->once()->andReturn([$this->staging('Trupp 2', '2025-10-11')]);
        });

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('Read 2 card(s) from 3 Planka list(s).')
            ->assertSuccessful();

        $this->assertSame(2, Performance::query()->count());

        foreach (['list-1', 'list-2', 'list-3'] as $listId) {
            Http::assertSent(fn ($request) => $request->url() === "https://planka.test/api/lists/{$listId}");
        }
    }

    public function test_it_hands_the_card_to_the_ai_whole(): void
    {
        $this->fakeBoard([$this->card()]);

        $this->mock(PlankaPerformanceExtractor::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('extract')
            ->once()
            ->with('13.09 õhtu', 'Toimumise kuupäev: 13.09.2025', '2025-09-13T15:00:00.000Z')
            ->andReturn([]));

        $this->artisan('planka:import-performances')->assertSuccessful();
    }

    public function test_it_signs_its_requests_with_the_api_key_header(): void
    {
        $this->fakeBoard([]);
        $this->fakeExtraction([]);

        $this->artisan('planka:import-performances')->assertSuccessful();

        // Planka refuses the same value on an Authorization header.
        Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-token')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_it_refuses_to_run_unconfigured(): void
    {
        config()->set('services.planka.list_ids', '');

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('Planka is not configured')
            ->assertFailed();
    }

    public function test_it_reports_a_board_that_will_not_answer(): void
    {
        Http::fake(['planka.test/*' => Http::response(['message' => 'nope'], 500)]);

        $this->artisan('planka:import-performances')
            ->expectsOutputToContain('Could not read the Planka lists')
            ->assertFailed();
    }
}
