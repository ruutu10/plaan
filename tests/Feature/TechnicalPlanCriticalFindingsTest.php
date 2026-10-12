<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Models\TechnicalPlan;
use App\Services\TechnicalPlanCriticalFindings;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * The second pass over a submitted plan's review: what it is shown, and what
 * it makes of the answer.
 */
class TechnicalPlanCriticalFindingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The bodies sent to the API, in order.
     *
     * @var list<array<string, mixed>>
     */
    private array $sentBodies = [];

    public function test_the_second_pass_is_shown_the_plan_alongside_the_review(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create([
            'scenes' => [
                ['id' => 'stseen-1', 'name' => 'Suur finaal', 'light' => '', 'sounds' => [], 'sound' => 'Lõpumuusika', 'notes' => ''],
            ],
        ]);

        $this->siftingAnswering('{"criticalFindings": [], "reasoningNotes": []}')
            ->findIn($plan, 'Stseenil „Suur finaal" pole helifaili.');

        $userMessage = $this->sentBodies[0]['messages'][0]['content'];

        $this->assertStringContainsString('<plan>', $userMessage);
        $this->assertStringContainsString('"name": "Suur finaal"', $userMessage);
        $this->assertStringContainsString('"sound": "Lõpumuusika"', $userMessage);
        $this->assertStringContainsString("<review>\nStseenil „Suur finaal\" pole helifaili.\n</review>", $userMessage);
    }

    public function test_the_findings_come_back_as_a_list_of_lines(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $findings = $this->siftingAnswering((string) json_encode([
            'criticalFindings' => ['Lisa „Finaali" helifail.', '  ', 3],
            'reasoningNotes' => [],
        ]))
            ->findIn($plan, 'ülevaatus');

        $this->assertSame(['Lisa „Finaali" helifail.', '3'], $findings);
    }

    public function test_an_empty_review_is_not_sent_at_all(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->assertSame([], $this->siftingAnswering('{}')->findIn($plan, '   '));
        $this->assertSame([], $this->sentBodies);
    }

    public function test_an_unreadable_answer_finds_nothing(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->assertSame([], $this->siftingAnswering('not json')->findIn($plan, 'ülevaatus'));
    }

    /**
     * A real second pass talking to a transport that always answers with the
     * given text, recording what it was sent.
     */
    private function siftingAnswering(string $text): TechnicalPlanCriticalFindings
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

        return (new TechnicalPlanCriticalFindings(
            new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transporter]),
        ))->withoutClaudeCache();
    }
}
