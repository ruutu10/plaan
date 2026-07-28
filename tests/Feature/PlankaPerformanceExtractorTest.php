<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Models\Team;
use App\Services\PlankaPerformanceExtractor;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class PlankaPerformanceExtractorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The bodies the extractor sent, in order, so a test can look at the
     * request it built instead of only the answer it parsed.
     *
     * @var list<array<string, mixed>>
     */
    private array $sentBodies = [];

    /**
     * An extractor talking to a transport that always answers with the given
     * text, as if it were the model's single content block.
     */
    private function extractorAnswering(string $text): PlankaPerformanceExtractor
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
                /** @var array<string, mixed> $body */
                $body = json_decode((string) $request->getBody(), true) ?: [];
                $this->sentBodies[] = $body;

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

        return new PlankaPerformanceExtractor(
            new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transporter]),
        );
    }

    public function test_it_turns_the_ai_answer_into_stagings(): void
    {
        $extractor = $this->extractorAnswering((string) json_encode([
            'performances' => [
                ['show_name' => 'Trupp 1', 'date' => '2025-09-13', 'duration_minutes' => 90],
                ['show_name' => 'JadaJada Special', 'date' => '2025-09-13', 'duration_minutes' => null],
            ],
        ]));

        $performances = $extractor->extract(
            'TLN Duubel: R10 ja JadaJada etendus',
            "- **Toimumise kuupäev:** 13.09.2025\n\n**Show 18:00-19:30**\nTrupp 1 - Martin, Trent, Rauno\n\n**Show 20:00-21:30**\nJadaJada Special",
            '2025-09-13T15:00:00.000Z',
        );

        $this->assertCount(2, $performances);

        $this->assertSame('Trupp 1', $performances[0]->showName);
        $this->assertSame('2025-09-13', $performances[0]->date->toDateString());
        $this->assertSame(90, $performances[0]->duration);

        $this->assertSame('JadaJada Special', $performances[1]->showName);
        $this->assertNull($performances[1]->duration);
    }

    public function test_it_asks_for_a_schema_constrained_answer(): void
    {
        $this->extractorAnswering('{"performances": []}')->extract(
            '13.09 õhtu',
            'Toimumise kuupäev: 13.09.2025',
            '2025-09-13T15:00:00.000Z',
        );

        $body = $this->sentBodies[0];

        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame(
            ['show_name', 'date', 'duration_minutes', 'team_id'],
            $body['output_config']['format']['schema']['properties']['performances']['items']['required'],
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
        $this->extractorAnswering('{"performances": []}')->extract('Tühi', 'Kaardi tekst');

        $this->assertStringContainsString('puudub', $this->sentBodies[0]['messages'][0]['content']);
    }

    public function test_it_lists_the_groups_a_show_can_be_handed_to(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);
        $personal = Team::factory()->create(['name' => "ando's Team", 'is_personal' => true]);

        $this->extractorAnswering('{"performances": []}')->extract('13.09 õhtu', 'Kaardi tekst');

        $prompt = $this->sentBodies[0]['messages'][0]['content'];

        $this->assertStringContainsString("- {$team->id} — Tsikid Reas", $prompt);

        // A personal team stands for a person's corner of the app, not a group.
        $this->assertStringNotContainsString("ando's Team", $prompt);
        $this->assertStringNotContainsString("- {$personal->id} —", $prompt);

        $schema = $this->sentBodies[0]['output_config']['format']['schema'];
        $this->assertContains('team_id', $schema['properties']['performances']['items']['required']);
    }

    public function test_it_says_so_when_there_are_no_groups_to_hand_a_show_to(): void
    {
        $this->extractorAnswering('{"performances": []}')->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertStringContainsString('Truppe pole registreeritud', $this->sentBodies[0]['messages'][0]['content']);
    }

    public function test_it_keeps_a_group_the_ai_matched(): void
    {
        $team = Team::factory()->create(['name' => 'Tsikid Reas']);

        $performances = $this->extractorAnswering((string) json_encode([
            'performances' => [
                ['show_name' => 'Tšikid reas', 'date' => '2025-08-14', 'duration_minutes' => 120, 'team_id' => $team->id],
            ],
        ]))->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertSame($team->id, $performances[0]->teamId);
    }

    public function test_it_refuses_a_group_that_does_not_exist(): void
    {
        Team::factory()->create(['name' => 'Tsikid Reas']);

        $performances = $this->extractorAnswering((string) json_encode([
            'performances' => [
                ['show_name' => 'Trupp 1', 'date' => '2025-08-14', 'duration_minutes' => null, 'team_id' => 4242],
            ],
        ]))->extract('13.09 õhtu', 'Kaardi tekst');

        $this->assertNull($performances[0]->teamId);
    }

    public function test_it_drops_entries_it_cannot_use(): void
    {
        $extractor = $this->extractorAnswering((string) json_encode([
            'performances' => [
                ['show_name' => '', 'date' => '2025-09-13', 'duration_minutes' => 90],
                ['show_name' => 'Trupp 1', 'date' => 'kevadel', 'duration_minutes' => 90],
                ['show_name' => 'Trupp 2', 'date' => '2025-09-13', 'duration_minutes' => 0],
            ],
        ]));

        $performances = $extractor->extract('13.09 õhtu', 'Toimumise kuupäev: 13.09.2025');

        $this->assertCount(1, $performances);
        $this->assertSame('Trupp 2', $performances[0]->showName);
        $this->assertNull($performances[0]->duration);
    }

    public function test_an_answer_that_is_not_a_performance_list_yields_nothing(): void
    {
        $this->assertSame([], $this->extractorAnswering('Vabandust, ma ei oska.')->extract('Tühi', 'Tühi kaart'));
    }
}
