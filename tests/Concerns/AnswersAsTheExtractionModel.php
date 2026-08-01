<?php

namespace Tests\Concerns;

use Anthropic\Client;
use App\Services\PlankaPerformanceExtractor;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A real {@see PlankaPerformanceExtractor} talking to a transport that answers
 * with a canned message, so a test can exercise the prompt it builds and the
 * JSON it parses without an API key.
 */
trait AnswersAsTheExtractionModel
{
    /**
     * The bodies the extractor sent, in order, so a test can look at the
     * request it built instead of only the answer it parsed.
     *
     * @var list<array<string, mixed>>
     */
    protected array $sentBodies = [];

    /**
     * An extractor talking to a transport that always answers with the given
     * text, as if it were the model's single content block.
     */
    protected function extractorAnswering(string $text): PlankaPerformanceExtractor
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
}
