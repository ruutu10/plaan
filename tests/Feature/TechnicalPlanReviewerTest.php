<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Models\TechnicalPlan;
use App\Services\TechnicalPlanReviewer;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * The first pass over a plan: what the reviewer is sent.
 */
class TechnicalPlanReviewerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The bodies sent to the API, in order.
     *
     * @var list<array<string, mixed>>
     */
    private array $sentBodies = [];

    /**
     * Every plan is reviewed under the same persona and instructions, so the
     * system prompt is marked for the API's prompt cache and only the plan is
     * paid for in full — the same prefix for a performer asking again after an
     * edit, and for the next performer.
     */
    public function test_the_system_prompt_is_marked_for_the_prompt_cache(): void
    {
        $reviewer = $this->reviewerAnswering('Kõik on korras.');

        $reviewer->reviewMarkdown(TechnicalPlan::factory()->create(['extra' => ['notes' => 'Esimene plaan', 'files' => []]]));
        $reviewer->reviewMarkdown(TechnicalPlan::factory()->create(['extra' => ['notes' => 'Teine plaan', 'files' => []]]));

        [$first, $second] = $this->sentBodies;

        $this->assertCount(1, $first['system']);
        $this->assertSame(['type' => 'ephemeral'], $first['system'][0]['cache_control']);
        $this->assertNotSame('', $first['system'][0]['text']);
        $this->assertSame($first['system'], $second['system']);

        // The plan itself differs every time, so it is left out of the prefix.
        $this->assertIsString($first['messages'][0]['content']);
        $this->assertStringContainsString('Esimene plaan', $first['messages'][0]['content']);
        $this->assertStringContainsString('Teine plaan', $second['messages'][0]['content']);
    }

    public function test_the_review_comes_back_in_the_models_own_words(): void
    {
        $review = $this->reviewerAnswering("  Lisa finaalile helifail.\n")
            ->reviewMarkdown(TechnicalPlan::factory()->create());

        $this->assertSame('Lisa finaalile helifail.', $review);
    }

    /**
     * A real reviewer talking to a transport that always answers with the given
     * text, recording what it was sent.
     */
    private function reviewerAnswering(string $text): TechnicalPlanReviewer
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

        return (new TechnicalPlanReviewer(
            new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transporter]),
        ))->withoutClaudeCache();
    }
}
