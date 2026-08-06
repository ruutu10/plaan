<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\AnswersAsTheExtractionModel;
use Tests\TestCase;

class PlankaPerformanceExtractorTest extends TestCase
{
    use AnswersAsTheExtractionModel, RefreshDatabase;

    public function test_it_turns_the_ai_answer_into_nights(): void
    {
        $extractor = $this->extractorAnswering((string) json_encode([
            'formats' => [
                [
                    'format_name' => 'Trupp 1',
                    'date' => '2025-09-13',
                    'performances' => [['title' => 'Trupp 1', 'duration_minutes' => 90]],
                ],
                [
                    'format_name' => 'JadaJada Special',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null, 'duration_minutes' => null]],
                ],
            ],
        ]));

        $nights = $extractor->extract(
            'TLN Duubel: R10 ja JadaJada etendus',
            "- **Toimumise kuupäev:** 13.09.2025\n\n**Format 18:00-19:30**\nTrupp 1 - Martin, Trent, Rauno\n\n**Format 20:00-21:30**\nJadaJada Special",
            '2025-09-13T15:00:00.000Z',
        );

        $this->assertCount(2, $nights);

        $this->assertSame('Trupp 1', $nights[0]->formatName);
        $this->assertSame('2025-09-13', $nights[0]->date->toDateString());
        $this->assertCount(1, $nights[0]->performances);
        $this->assertSame(90, $nights[0]->performances[0]->duration);

        $this->assertSame('JadaJada Special', $nights[1]->formatName);
        $this->assertNull($nights[1]->performances[0]->duration);
    }

    public function test_it_reads_an_evening_several_groups_share_as_one_format(): void
    {
        $marturu = Team::factory()->create(['name' => 'Märtu10']);
        $matu = Team::factory()->create(['name' => 'Mätu']);

        // The card the change exists for: four acts, one after the other, on
        // one night. Each is a performance of the same format.
        $nights = $this->extractorAnswering((string) json_encode([
            'formats' => [
                [
                    'format_name' => 'Õppelava',
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
        $this->assertSame('Õppelava', $nights[0]->formatName);
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

    public function test_a_lone_act_named_after_its_format_carries_no_title_of_its_own(): void
    {
        // The format's own name already says who is playing, so repeating it on
        // the performance would only put the same word on the screen twice —
        // and every performance already on the books has none.
        $nights = $this->extractorAnswering((string) json_encode([
            'formats' => [
                [
                    'format_name' => 'Bitseption',
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
            'formats' => [
                ['format_name' => 'Tšikid reas', 'date' => '2025-09-13', 'team_id' => $team->id, 'performances' => []],
            ],
        ]))->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertCount(1, $nights[0]->performances);
        $this->assertNull($nights[0]->performances[0]->title);
        $this->assertSame($team->id, $nights[0]->performances[0]->teamId);
    }

    public function test_it_reads_the_hour_a_card_names(): void
    {
        $nights = $this->extractorAnswering((string) json_encode([
            'formats' => [
                [
                    'format_name' => 'Trupp 1',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null, 'start_time' => '18:00', 'duration_minutes' => 90]],
                ],
            ],
        ]))->extract('13.09 õhtu', 'Format 18:00-19:30', null);

        $this->assertSame('18:00', $nights[0]->performances[0]->startTime);
    }

    public function test_an_hour_that_is_not_a_time_of_day_is_left_to_the_house(): void
    {
        // A board is written by hand, and the model passes on what it finds.
        // Anything the format does not cover is better left empty than guessed
        // at — the importer falls back to the venue's usual curtain-up.
        foreach ([null, '', 'õhtul', 'kell 7', '25:00', '7pm'] as $unreadable) {
            $nights = $this->extractorAnswering((string) json_encode([
                'formats' => [
                    [
                        'format_name' => 'Trupp 1',
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
        $this->extractorAnswering('{"formats": []}')->extract(
            '13.09 õhtu',
            'Toimumise kuupäev: 13.09.2025',
            '2025-09-13T15:00:00.000Z',
        );

        $body = $this->sentBodies[0];
        $formats = $body['output_config']['format']['schema']['properties']['formats'];

        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame(
            ['format_name', 'date', 'team_id', 'performances'],
            $formats['items']['required'],
        );
        $this->assertSame(
            ['title', 'start_time', 'duration_minutes', 'team_id', 'staff'],
            $formats['items']['properties']['performances']['items']['required'],
        );
        $this->assertSame(
            ['name', 'role'],
            $formats['items']['properties']['performances']['items']['properties']['staff']['items']['required'],
        );
        $this->assertSame(
            PerformanceStaffRole::values(),
            $formats['items']['properties']['performances']['items']['properties']['staff']['items']['properties']['role']['enum'],
        );

        // The reading is only debuggable if the model says how it read the card.
        $schema = $body['output_config']['format']['schema'];

        $this->assertSame(['formats', 'reasoningNotes'], $schema['required']);
        $this->assertSame('array', $schema['properties']['reasoningNotes']['type']);
        $this->assertSame('string', $schema['properties']['reasoningNotes']['items']['type']);
        $this->assertStringContainsString('reasoningNotes', $body['system']);

        // The card must reach the model whole — title, description and the due
        // date that supplies the year for dates written without one.
        $this->assertStringContainsString('13.09 õhtu', $body['messages'][0]['content']);
        $this->assertStringContainsString('Toimumise kuupäev: 13.09.2025', $body['messages'][0]['content']);
        $this->assertStringContainsString('2025-09-13', $body['messages'][0]['content']);
        $this->assertStringContainsString('Ruutu10', $body['system']);
    }

    public function test_a_card_without_a_due_date_still_reaches_the_model(): void
    {
        $this->extractorAnswering('{"formats": []}')->extract('Tühi', 'Kaardi tekst');

        $this->assertStringContainsString('puudub', $this->sentBodies[0]['messages'][0]['content']);
    }

    public function test_it_lists_the_groups_a_format_can_be_handed_to(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);

        $this->extractorAnswering('{"formats": []}')->extract('13.09 õhtu', 'Kaardi tekst');

        $prompt = $this->sentBodies[0]['messages'][0]['content'];

        $this->assertStringContainsString("- {$team->id} — Tsikid Reas", $prompt);

        $formats = $this->sentBodies[0]['output_config']['format']['schema']['properties']['formats'];

        // Asked for at both levels: the format's owner, and who plays each act.
        $this->assertContains('team_id', $formats['items']['required']);
        $this->assertContains('team_id', $formats['items']['properties']['performances']['items']['required']);
    }

    public function test_the_cards_own_labels_reach_the_model(): void
    {
        $this->extractorAnswering('{"formats": []}')->extract(
            '13.09 õhtu',
            'Kaardi tekst',
            null,
            ['ETENDUS', 'FESTIVAL'],
        );

        $prompt = $this->sentBodies[0]['messages'][0]['content'];

        $this->assertStringContainsString('# Kaardi sildid', $prompt);
        $this->assertStringContainsString('- ETENDUS', $prompt);
        $this->assertStringContainsString('- FESTIVAL', $prompt);
        // And the system prompt says what they are, or they are just words.
        $this->assertStringContainsString('Sildid', $this->sentBodies[0]['system']);
    }

    public function test_a_card_carrying_no_labels_says_so(): void
    {
        $this->extractorAnswering('{"formats": []}')->extract('13.09 õhtu', 'Kaardi tekst');

        // Said outright rather than left as an empty heading: an empty section
        // reads as a card whose labels went missing on the way here.
        $this->assertStringContainsString('Sildid puuduvad.', $this->sentBodies[0]['messages'][0]['content']);
    }

    public function test_it_says_so_when_there_are_no_groups_to_hand_a_format_to(): void
    {
        $this->extractorAnswering('{"formats": []}')->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertStringContainsString('Tiime pole registreeritud', $this->sentBodies[0]['messages'][0]['content']);
    }

    public function test_it_keeps_a_group_the_ai_matched(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);

        $nights = $this->extractorAnswering((string) json_encode([
            'formats' => [
                [
                    'format_name' => 'Tšikid reas',
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
            'formats' => [
                [
                    'format_name' => 'Trupp 1',
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
            'formats' => [
                ['format_name' => '', 'date' => '2025-09-13', 'performances' => [['title' => null]]],
                ['format_name' => 'Trupp 1', 'date' => 'kevadel', 'performances' => [['title' => null]]],
                [
                    'format_name' => 'Trupp 2',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null, 'duration_minutes' => 0]],
                ],
            ],
        ]));

        $nights = $extractor->extract('13.09 õhtu', 'Toimumise kuupäev: 13.09.2025');

        $this->assertCount(1, $nights);
        $this->assertSame('Trupp 2', $nights[0]->formatName);
        $this->assertNull($nights[0]->performances[0]->duration);
    }

    public function test_it_reads_the_staff_the_model_named(): void
    {
        $nights = $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'performances' => [[
                    'title' => 'Märtu10',
                    'staff' => [
                        ['name' => 'Arne', 'role' => 'host'],
                        ['name' => 'Tom', 'role' => 'technician'],
                    ],
                ]],
            ]],
        ]))->extract('Õppelava 9.10', 'Õhtujuht: Arne');

        $staff = $nights[0]->performances[0]->staff;

        $this->assertCount(2, $staff);
        $this->assertSame('Arne', $staff[0]->name);
        $this->assertSame(PerformanceStaffRole::Host, $staff[0]->role);
        $this->assertSame('Tom', $staff[1]->name);
        $this->assertSame(PerformanceStaffRole::Technician, $staff[1]->role);
    }

    public function test_it_drops_a_staff_entry_with_no_name_or_an_unknown_role(): void
    {
        $nights = $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Õppelava',
                'date' => '2025-10-09',
                'performances' => [[
                    'title' => 'Märtu10',
                    // A role outside the enum cannot come back from a real,
                    // schema-constrained answer, but the parser is defensive
                    // about it anyway — see readStaff()'s doc comment.
                    'staff' => [
                        ['name' => '', 'role' => 'host'],
                        ['name' => 'Marju', 'role' => 'project-manager'],
                        ['name' => 'Tom', 'role' => 'technician'],
                    ],
                ]],
            ]],
        ]))->extract('Õppelava 9.10', 'Kaardi tekst');

        $staff = $nights[0]->performances[0]->staff;

        $this->assertCount(1, $staff);
        $this->assertSame('Tom', $staff[0]->name);
    }

    public function test_the_title_stripped_from_a_lone_act_keeps_its_staff(): void
    {
        $nights = $this->extractorAnswering((string) json_encode([
            'formats' => [[
                'format_name' => 'Trupp 1',
                'date' => '2025-09-13',
                'performances' => [[
                    'title' => 'Trupp 1',
                    'staff' => [['name' => 'Ando', 'role' => 'technician']],
                ]],
            ]],
        ]))->extract('13.09 õhtu', 'Heli- ja valgus: Ando');

        $this->assertNull($nights[0]->performances[0]->title);
        $this->assertCount(1, $nights[0]->performances[0]->staff);
        $this->assertSame('Ando', $nights[0]->performances[0]->staff[0]->name);
    }

    public function test_an_answer_that_is_not_a_night_list_yields_nothing(): void
    {
        $this->assertSame([], $this->extractorAnswering('Vabandust, ma ei oska.')->extract('Tühi', 'Tühi kaart'));
    }

    public function test_it_logs_why_the_model_read_the_card_the_way_it_did(): void
    {
        Log::spy();

        $this->extractorAnswering((string) json_encode([
            'formats' => [
                [
                    'format_name' => 'Trupp 1',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null]],
                ],
            ],
            'reasoningNotes' => [
                'Kuupäev real "Toimumise kuupäev: 13.09.2025".',
                'Ükski tiim ei sobinud, seega team_id on tühi.',
                '   ',
                ['mitte lause'],
            ],
        ]))->extract('13.09 õhtu', 'Toimumise kuupäev: 13.09.2025');

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Read a Planka card'
                && $context['reasoningNotes'] === [
                    'Kuupäev real "Toimumise kuupäev: 13.09.2025".',
                    'Ükski tiim ei sobinud, seega team_id on tühi.',
                ])
            ->once();
    }

    public function test_an_answer_without_notes_is_still_read(): void
    {
        Log::spy();

        $nights = $this->extractorAnswering((string) json_encode([
            'formats' => [
                [
                    'format_name' => 'Trupp 1',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null]],
                ],
            ],
        ]))->extract('13.09 õhtu', 'Toimumise kuupäev: 13.09.2025');

        $this->assertCount(1, $nights);

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Read a Planka card'
                && $context['reasoningNotes'] === [])
            ->once();
    }

    public function test_it_keeps_the_models_answer_whole(): void
    {
        $answer = [
            'formats' => [
                [
                    'format_name' => 'Trupp 1',
                    'date' => '2025-09-13',
                    'performances' => [['title' => null]],
                ],
            ],
            'reasoningNotes' => ['Kuupäev real "Toimumise kuupäev: 13.09.2025".'],
        ];

        $extractor = $this->extractorAnswering((string) json_encode($answer));
        $extractor->extract('13.09 õhtu', 'Toimumise kuupäev: 13.09.2025');

        $this->assertSame($answer, $extractor->rawResponse());
    }

    public function test_an_answer_that_is_not_a_night_list_keeps_no_raw_response(): void
    {
        $extractor = $this->extractorAnswering('Vabandust, ma ei oska.');
        $extractor->extract('Tühi', 'Tühi kaart');

        $this->assertNull($extractor->rawResponse());
    }
}
