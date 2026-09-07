<?php

namespace Tests\Feature;

use App\Events\PlankaReimportRequested;
use App\Http\Controllers\PerformancePlankaImportController;
use App\Listeners\ImportPlankaCardsForPerformance;
use App\Models\Format;
use App\Models\Performance;
use App\Data\ImportedNight;
use App\Data\ImportedPerformance;
use App\Models\User;
use App\Services\PlankaPerformanceExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Reading one performance off the Planka board again on request — see
 * {@see PerformancePlankaImportController}.
 *
 * The screen's button is the whole of it: the crew presses it when a card has
 * just changed and the nightly run is too far off. What matters here is who may
 * press it, that nothing is imported in the request itself, and that the run is
 * narrowed to the name the screen heads the performance with.
 */
class PerformancePlankaReimportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.planka.url', 'https://planka.test');
        config()->set('services.planka.list_ids', 'list-1');
        config()->set('services.planka.token', 'test-token');
    }

    public function test_guests_are_turned_away(): void
    {
        Event::fake();

        $performance = Performance::factory()->create();

        $this->postJson($this->reimportUrl($performance))->assertUnauthorized();

        Event::assertNotDispatched(PlankaReimportRequested::class);
    }

    public function test_the_crew_may_set_a_reading_going(): void
    {
        Event::fake();

        $format = Format::factory()->create(['name' => 'Improkolmapäev']);
        $performance = Performance::factory()->for($format)->create(['title' => null]);
        $technician = $this->technician();

        $this->actingAs($technician)
            ->postJson($this->reimportUrl($performance))
            ->assertAccepted()
            ->assertJson(['filterTitle' => 'Improkolmapäev']);

        Event::assertDispatched(
            PlankaReimportRequested::class,
            fn (PlankaReimportRequested $event): bool => $event->performance->is($performance)
                && $event->filterTitle === 'Improkolmapäev'
                && $event->requestedBy->is($technician),
        );
    }

    public function test_an_act_of_a_shared_evening_is_read_by_its_own_name(): void
    {
        Event::fake();

        $format = Format::factory()->create(['name' => 'Õppelava']);
        $performance = Performance::factory()->for($format)->create(['title' => 'Rühm B']);

        $this->actingAs($this->technician())
            ->postJson($this->reimportUrl($performance))
            ->assertAccepted()
            ->assertJson(['filterTitle' => 'Rühm B']);

        Event::assertDispatched(
            PlankaReimportRequested::class,
            fn (PlankaReimportRequested $event): bool => $event->filterTitle === 'Rühm B',
        );
    }

    public function test_the_group_whose_night_it_is_may_not_set_one_going(): void
    {
        Event::fake();

        // A member of the format's own group: allowed to edit the performance,
        // and still refused this, because the run reaches the whole board.
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->for($format)->create();

        $this->actingAs($user)
            ->postJson($this->reimportUrl($performance))
            ->assertForbidden();

        Event::assertNotDispatched(PlankaReimportRequested::class);
    }

    public function test_nothing_can_be_read_when_no_board_is_configured(): void
    {
        Event::fake();

        config()->set('services.planka.token', null);

        $performance = Performance::factory()->create();

        $this->actingAs($this->technician())
            ->postJson($this->reimportUrl($performance))
            ->assertForbidden();

        Event::assertNotDispatched(PlankaReimportRequested::class);
    }

    public function test_a_performance_cannot_be_reached_through_another_formats_url(): void
    {
        Event::fake();

        $performance = Performance::factory()->create();
        $otherFormat = Format::factory()->create();

        $this->actingAs($this->technician())
            ->postJson(route('api.formats.performances.planka-import', [$otherFormat, $performance]))
            ->assertNotFound();

        Event::assertNotDispatched(PlankaReimportRequested::class);
    }

    public function test_the_details_json_offers_the_button_to_the_crew_alone(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->for($format)->create();

        $url = route('api.formats.performances.show', [$format, $performance]);

        $this->actingAs($user)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.canReimportFromPlanka', false);

        $this->actingAs($this->technician())
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.canReimportFromPlanka', true);
    }

    public function test_the_button_is_withheld_when_no_board_is_configured(): void
    {
        config()->set('services.planka.token', null);

        $performance = Performance::factory()->create();

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertJsonPath('data.canReimportFromPlanka', false);
    }

    public function test_the_reading_is_queued_rather_than_done_in_the_request(): void
    {
        $listener = new ImportPlankaCardsForPerformance;

        $this->assertInstanceOf(ShouldQueue::class, $listener);

        // Two presses on one title are one run: the same lock, whatever case
        // the board writes the name in.
        $format = Format::factory()->create(['name' => 'Õppelava']);
        $performance = Performance::factory()->for($format)->create(['title' => 'Rühm B']);

        $this->assertSame(
            'rühm b',
            $listener->uniqueId(new PlankaReimportRequested(
                $performance,
                $performance->plankaImportFilter(),
                $this->technician(),
            )),
        );
    }

    public function test_the_reading_takes_in_this_performances_card_and_no_other(): void
    {
        $format = Format::factory()->create(['name' => 'Improkolmapäev']);
        $performance = Performance::factory()->for($format)->create([
            'title' => null,
            'date' => Carbon::parse('2025-09-06 19:00', Performance::venueTimezone()),
        ]);

        $this->fakeBoard([
            $this->card('card-1', 'Improkolmapäev 13.09'),
            $this->card('card-2', 'Sketšikas 20.09'),
        ]);

        // Called once, for the one card the title kept: the other is dropped
        // before it is ever read, which is the whole point of the narrowing.
        $this->mock(PlankaPerformanceExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')
                ->once()
                ->withArgs(fn (string $cardName): bool => $cardName === 'Improkolmapäev 13.09')
                ->andReturn([new ImportedNight(
                    formatName: 'Improkolmapäev',
                    date: Carbon::parse('2025-09-13'),
                    teamId: null,
                    location: 'Vaba Lava',
                    performances: [new ImportedPerformance(startTime: '19:00', duration: 90)],
                )]);
            $mock->shouldReceive('reasoningNotes')->andReturn([]);
        });

        // Fired for real on the sync queue the test suite runs, so the listener
        // and the command it calls are both exercised.
        PlankaReimportRequested::dispatch(
            $performance,
            $performance->plankaImportFilter(),
            $this->technician(),
        );

        $this->assertDatabaseHas('performances', [
            'format_id' => $format->id,
            'planka_card_id' => 'card-1',
            'location' => 'Vaba Lava',
            'is_draft' => true,
        ]);

        // Nothing was invented for the card the filter dropped.
        $this->assertDatabaseMissing('performances', ['planka_card_id' => 'card-2']);
    }

    /**
     * One watched list, which Planka serves with its cards beside it.
     *
     * @param  list<array<string, mixed>>  $cards
     */
    private function fakeBoard(array $cards): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'planka.test/api/boards/board-1' => Http::response([
                'item' => ['id' => 'board-1'],
                'included' => ['labels' => []],
            ]),
            'planka.test/api/lists/list-1' => Http::response([
                'item' => ['id' => 'list-1', 'name' => 'List 1', 'boardId' => 'board-1'],
                'included' => ['cards' => $cards, 'cardLabels' => []],
            ]),
        ]);
    }

    /**
     * One card as Planka serves it.
     *
     * @return array<string, mixed>
     */
    private function card(string $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'description' => 'Toimumise kuupäev: 13.09.2025',
            'dueDate' => '2025-09-13T16:00:00.000Z',
        ];
    }

    private function reimportUrl(Performance $performance): string
    {
        return route('api.formats.performances.planka-import', [$performance->format, $performance]);
    }
}
