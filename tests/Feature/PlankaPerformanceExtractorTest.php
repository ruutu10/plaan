<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AnswersAsTheExtractionModel;
use Tests\TestCase;

class PlankaPerformanceExtractorTest extends TestCase
{
    use AnswersAsTheExtractionModel, RefreshDatabase;

    public function test_it_turns_the_ai_answer_into_nights(): void
    {
        $extractor = $this->extractorAnswering((string) json_encode([
            'shows' => [
                [
                    'show_name' => 'Trupp 1',
                    'date' => '2025-09-13',
                    'performances' => [['title' => 'Trupp 1', 'duration_minutes' => 90]],
                ],
                [
                    'show_name' => 'JadaJada Special',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null, 'duration_minutes' => null]],
                ],
            ],
        ]));

        $nights = $extractor->extract(
            'TLN Duubel: R10 ja JadaJada etendus',
            "- **Toimumise kuupäev:** 13.09.2025\n\n**Show 18:00-19:30**\nTrupp 1 - Martin, Trent, Rauno\n\n**Show 20:00-21:30**\nJadaJada Special",
            '2025-09-13T15:00:00.000Z',
        );

        $this->assertCount(2, $nights);

        $this->assertSame('Trupp 1', $nights[0]->showName);
        $this->assertSame('2025-09-13', $nights[0]->date->toDateString());
        $this->assertCount(1, $nights[0]->performances);
        $this->assertSame(90, $nights[0]->performances[0]->duration);

        $this->assertSame('JadaJada Special', $nights[1]->showName);
        $this->assertNull($nights[1]->performances[0]->duration);
    }

    public function test_it_reads_an_evening_several_groups_share_as_one_show(): void
    {
        $marturu = Team::factory()->create(['name' => 'Märtu10']);
        $matu = Team::factory()->create(['name' => 'Mätu']);

        // The card the change exists for: four acts, one after the other, on
        // one night. Each is a performance of the same show.
        $nights = $this->extractorAnswering((string) json_encode([
            'shows' => [
                [
                    'show_name' => 'Õppelava',
                    'date' => '2025-10-09',
                    'team_id' => null,
                    'performances' => [
                        ['title' => 'Märtu10', 'start_time' => '20:00', 'duration_minutes' => 20, 'team_id' => $marturu->id],
                        ['title' => 'Tõnis ilma Tanelita külalisega', 'start_time' => '20:20', 'duration_minutes' => 30, 'team_id' => null],
                        ['title' => 'Mätu', 'start_time' => '20:50', 'duration_minutes' => 30, 'team_id' => $matu->id],
                        ['title' => 'Improräpp', 'start_time' => '21:20', 'duration_minutes' => 30, 'team_id' => null],
                    ],
                ],
            ],
        ]))->extract('Õppelava 9.10', 'Esinejad: Märtu10 (20min), ...', '2025-10-09T15:00:00.000Z');

        $this->assertCount(1, $nights);
        $this->assertSame('Õppelava', $nights[0]->showName);
        $this->assertSame('2025-10-09', $nights[0]->date->toDateString());
        // The evening has no owner of its own; each act names its own group.
        $this->assertNull($nights[0]->teamId);

        $acts = $nights[0]->performances;

        $this->assertCount(4, $acts);
        $this->assertSame(
            ['Märtu10', 'Tõnis ilma Tanelita külalisega', 'Mätu', 'Improräpp'],
            array_map(fn ($act): ?string => $act->title, $acts),
        );
        $this->assertSame(
            ['20:00', '20:20', '20:50', '21:20'],
            array_map(fn ($act): ?string => $act->startTime, $acts),
        );
        $this->assertSame([20, 30, 30, 30], array_map(fn ($act): ?int => $act->duration, $acts));
        $this->assertSame([$marturu->id, null, $matu->id, null], array_map(fn ($act): ?int => $act->teamId, $acts));
    }

    public function test_a_lone_act_named_after_its_show_carries_no_title_of_its_own(): void
    {
        // The show's own name already says who is playing, so repeating it on
        // the performance would only put the same word on the screen twice —
        // and every performance already on the books has none.
        $nights = $this->extractorAnswering((string) json_encode([
            'shows' => [
                [
                    'show_name' => 'Bitseption',
                    'date' => '2025-09-13',
                    'performances' => [['title' => 'BITSEPTION', 'duration_minutes' => 90]],
                ],
            ],
        ]))->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertNull($nights[0]->performances[0]->title);
        $this->assertSame(90, $nights[0]->performances[0]->duration);
    }

    public function test_a_night_the_model_broke_down_into_no_acts_is_still_played_once(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);

        $nights = $this->extractorAnswering((string) json_encode([
            'shows' => [
                ['show_name' => 'Tšikid reas', 'date' => '2025-09-13', 'team_id' => $team->id, 'performances' => []],
            ],
        ]))->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertCount(1, $nights[0]->performances);
        $this->assertNull($nights[0]->performances[0]->title);
        $this->assertSame($team->id, $nights[0]->performances[0]->teamId);
    }

    public function test_it_reads_the_hour_a_card_names(): void
    {
        $nights = $this->extractorAnswering((string) json_encode([
            'shows' => [
                [
                    'show_name' => 'Trupp 1',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null, 'start_time' => '18:00', 'duration_minutes' => 90]],
                ],
            ],
        ]))->extract('13.09 õhtu', 'Show 18:00-19:30', null);

        $this->assertSame('18:00', $nights[0]->performances[0]->startTime);
    }

    public function test_an_hour_that_is_not_a_time_of_day_is_left_to_the_house(): void
    {
        // A board is written by hand, and the model passes on what it finds.
        // Anything the format does not cover is better left empty than guessed
        // at — the importer falls back to the venue's usual curtain-up.
        foreach ([null, '', 'õhtul', 'kell 7', '25:00', '7pm'] as $unreadable) {
            $nights = $this->extractorAnswering((string) json_encode([
                'shows' => [
                    [
                        'show_name' => 'Trupp 1',
                        'date' => '2025-09-13',
                        'performances' => [['title' => null, 'start_time' => $unreadable]],
                    ],
                ],
            ]))->extract('13.09 õhtu', 'Kaardi tekst', null);

            $this->assertNull(
                $nights[0]->performances[0]->startTime,
                sprintf('Expected "%s" to be left to the house.', var_export($unreadable, true)),
            );
        }
    }

    public function test_it_asks_for_a_schema_constrained_answer(): void
    {
        $this->extractorAnswering('{"shows": []}')->extract(
            '13.09 õhtu',
            'Toimumise kuupäev: 13.09.2025',
            '2025-09-13T15:00:00.000Z',
        );

        $body = $this->sentBodies[0];
        $shows = $body['output_config']['format']['schema']['properties']['shows'];

        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame(
            ['show_name', 'date', 'team_id', 'performances'],
            $shows['items']['required'],
        );
        $this->assertSame(
            ['title', 'start_time', 'duration_minutes', 'team_id'],
            $shows['items']['properties']['performances']['items']['required'],
        );

        // The card must reach the model whole — title, description and the due
        // date that supplies the year for dates written without one.
        $this->assertStringContainsString('13.09 õhtu', $body['messages'][0]['content']);
        $this->assertStringContainsString('Toimumise kuupäev: 13.09.2025', $body['messages'][0]['content']);
        $this->assertStringContainsString('2025-09-13', $body['messages'][0]['content']);
        $this->assertStringContainsString('Ruutu10', $body['system']);
    }

    public function test_a_card_without_a_due_date_still_reaches_the_model(): void
    {
        $this->extractorAnswering('{"shows": []}')->extract('Tühi', 'Kaardi tekst');

        $this->assertStringContainsString('puudub', $this->sentBodies[0]['messages'][0]['content']);
    }

    public function test_it_lists_the_groups_a_show_can_be_handed_to(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);

        $this->extractorAnswering('{"shows": []}')->extract('13.09 õhtu', 'Kaardi tekst');

        $prompt = $this->sentBodies[0]['messages'][0]['content'];

        $this->assertStringContainsString("- {$team->id} — Tsikid Reas", $prompt);

        $shows = $this->sentBodies[0]['output_config']['format']['schema']['properties']['shows'];

        // Asked for at both levels: the show's owner, and who plays each act.
        $this->assertContains('team_id', $shows['items']['required']);
        $this->assertContains('team_id', $shows['items']['properties']['performances']['items']['required']);
    }

    public function test_it_says_so_when_there_are_no_groups_to_hand_a_show_to(): void
    {
        $this->extractorAnswering('{"shows": []}')->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertStringContainsString('Tiime pole registreeritud', $this->sentBodies[0]['messages'][0]['content']);
    }

    public function test_it_keeps_a_group_the_ai_matched(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);

        $nights = $this->extractorAnswering((string) json_encode([
            'shows' => [
                [
                    'show_name' => 'Tšikid reas',
                    'date' => '2025-08-14',
                    'team_id' => $team->id,
                    'performances' => [['title' => null, 'duration_minutes' => 120, 'team_id' => $team->id]],
                ],
            ],
        ]))->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertSame($team->id, $nights[0]->teamId);
        $this->assertSame($team->id, $nights[0]->performances[0]->teamId);
    }

    public function test_it_refuses_a_group_that_does_not_exist(): void
    {
        Team::factory()->create(['name' => 'Tsikid Reas']);

        $nights = $this->extractorAnswering((string) json_encode([
            'shows' => [
                [
                    'show_name' => 'Trupp 1',
                    'date' => '2025-08-14',
                    'team_id' => 4242,
                    'performances' => [['title' => null, 'team_id' => 4242]],
                ],
            ],
        ]))->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertNull($nights[0]->teamId);
        $this->assertNull($nights[0]->performances[0]->teamId);
    }

    public function test_it_drops_entries_it_cannot_use(): void
    {
        $extractor = $this->extractorAnswering((string) json_encode([
            'shows' => [
                ['show_name' => '', 'date' => '2025-09-13', 'performances' => [['title' => null]]],
                ['show_name' => 'Trupp 1', 'date' => 'kevadel', 'performances' => [['title' => null]]],
                [
                    'show_name' => 'Trupp 2',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null, 'duration_minutes' => 0]],
                ],
            ],
        ]));

        $nights = $extractor->extract('13.09 õhtu', 'Toimumise kuupäev: 13.09.2025');

        $this->assertCount(1, $nights);
        $this->assertSame('Trupp 2', $nights[0]->showName);
        $this->assertNull($nights[0]->performances[0]->duration);
    }

    public function test_an_answer_that_is_not_a_night_list_yields_nothing(): void
    {
        $this->assertSame([], $this->extractorAnswering('Vabandust, ma ei oska.')->extract('Tühi', 'Tühi kaart'));
    }
}
