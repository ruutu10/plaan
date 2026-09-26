<?php

namespace Tests\Feature;

use App\Data\ImportedNight;
use App\Data\ImportedPerformance;
use App\Events\PlankaReimportRequested;
use App\Http\Controllers\PerformancePlankaImportController;
use App\Listeners\ImportPlankaCardsForPerformance;
use App\Models\Format;
use App\Models\Performance;
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
 * press it, that nothing is imported in the request itself, and that the run
 * reads the performance's own card and nothing else — a performance that knows
 * no card is refused rather than guessed at by title.
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

        $performance = Performance::factory()->create(['planka_card_id' => 'card-1']);

        $this->postJson($this->reimportUrl($performance))->assertUnauthorized();

        Event::assertNotDispatched(PlankaReimportRequested::class);
    }

    public function test_the_crew_may_set_a_reading_of_the_performances_card_going(): void
    {
        Event::fake();

        $performance = Performance::factory()->create(['planka_card_id' => '1516073411733063234']);
        $technician = $this->technician();

        $this->actingAs($technician)
            ->postJson($this->reimportUrl($performance))
            ->assertAccepted()
            ->assertExactJson(['cardId' => '1516073411733063234']);

        Event::assertDispatched(
            PlankaReimportRequested::class,
            fn (PlankaReimportRequested $event): bool => $event->performance->is($performance)
                && $event->cardId === '1516073411733063234'
                && $event->requestedBy->is($technician),
        );
    }

    public function test_a_performance_that_knows_no_card_is_not_read_by_its_title(): void
    {
        Event::fake();

        $format = Format::factory()->create(['name' => 'Improkolmapäev']);
        $performance = Performance::factory()->for($format)->create([
            'title' => null,
            'planka_card_id' => null,
        ]);

        $this->actingAs($this->technician())
            ->postJson($this->reimportUrl($performance))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Etendusel pole Planka kaardi ID-d, mille järgi importida.');

        Event::assertNotDispatched(PlankaReimportRequested::class);
    }

    public function test_a_blank_card_id_counts_as_no_card(): void
    {
        Event::fake();

        $performance = Performance::factory()->create(['planka_card_id' => '']);

        $this->actingAs($this->technician())
            ->postJson($this->reimportUrl($performance))
            ->assertUnprocessable();

        Event::assertNotDispatched(PlankaReimportRequested::class);
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

        // Two presses on one card are one run — and so are two acts of one
        // shared evening, whose performances carry the same card.
        $performance = Performance::factory()->create(['planka_card_id' => 'card-7']);

        $this->assertSame(
            'card-7',
            $listener->uniqueId(new PlankaReimportRequested($performance, 'card-7', $this->technician())),
        );
    }

    public function test_the_reading_takes_in_this_performances_card_and_no_other(): void
    {
        $format = Format::factory()->create(['name' => 'Improkolmapäev']);
        $performance = Performance::factory()->for($format)->create([
            'title' => null,
            'date' => Carbon::parse('2025-09-13 19:00', Performance::venueTimezone()),
            'planka_card_id' => 'card-2',
            'location' => 'Vana saal',
        ]);

        // Both titles contain the format's name; only the performance's own card
        // is read.
        $this->fakeBoard([
            $this->card('card-1', 'Improkolmapäev 06.09'),
            $this->card('card-2', 'Improkolmapäev 13.09'),
        ]);

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

        // Pressed for real, with the listener on the sync queue the test suite
        // runs, so the controller, the listener and the command it calls are
        // all exercised.
        $this->actingAs($this->technician())
            ->postJson($this->reimportUrl($performance))
            ->assertAccepted();

        // The card's venue reached the performance it already described, and
        // no second one was made from it or from its neighbour.
        $this->assertSame('Vaba Lava', $performance->fresh()->location);
        $this->assertSame(1, Performance::query()->count());
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
