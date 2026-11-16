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
     * The `anthropic-beta` header of each request the extractor sent, in the
     * same order as {@see $sentBodies}: the betas a request opts into travel
     * there, not in the body.
     *
     * @var list<string>
     */
    protected array $sentBetaHeaders = [];

    /**
     * An extractor talking to a transport that always answers with the given
     * text, as if it were the model's single content block.
     */
    protected function extractorAnswering(string $text): PlankaPerformanceExtractor
    {
        return $this->extractorRespondingWith(['content' => [['type' => 'text', 'text' => $text]]]);
    }

    /**
     * An extractor talking to a transport that always answers with the given
     * message fields — its content blocks, its stop reason — laid over an
     * ordinary finished answer.
     *
     * @param  array<string, mixed>  $message
     */
    protected function extractorRespondingWith(array $message): PlankaPerformanceExtractor
    {
        $sentBodies = &$this->sentBodies;
        $sentBetaHeaders = &$this->sentBetaHeaders;

        $transporter = new class($message, $sentBodies, $sentBetaHeaders) implements ClientInterface
        {
            /**
             * @param  array<string, mixed>  $message
             * @param  list<array<string, mixed>>  $sentBodies
             * @param  list<string>  $sentBetaHeaders
             */
            public function __construct(private array $message, private array &$sentBodies, private array &$sentBetaHeaders)
            {
                //
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                /** @var array<string, mixed> $body */
                $body = json_decode((string) $request->getBody(), true) ?: [];
                $this->sentBodies[] = $body;
                $this->sentBetaHeaders[] = $request->getHeaderLine('anthropic-beta');

                return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                    'id' => 'msg_test',
                    'type' => 'message',
                    'role' => 'assistant',
                    'model' => 'claude-sonnet-5-5',
                    'content' => [],
                    'stop_reason' => 'end_turn',
                    'stop_sequence' => null,
                    'usage' => [
                        'input_tokens' => 120,
                        'cache_creation_input_tokens' => 300,
                        'cache_read_input_tokens' => 7000,
                        'output_tokens' => 40,
                    ],
                    ...$this->message,
                ]));
            }
        };

        return new PlankaPerformanceExtractor(
            new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transporter]),
        );
    }

    /**
     * The system prompt of a request the extractor sent, as one string,
     * whichever blocks it was sent in.
     */
    protected function sentSystemPrompt(int $request = 0): string
    {
        return $this->joinedText($this->sentBodies[$request]['system']);
    }

    /**
     * The user message of a request the extractor sent, as one string,
     * whichever blocks it was sent in.
     */
    protected function sentUserPrompt(int $request = 0): string
    {
        return $this->joinedText($this->sentBodies[$request]['messages'][0]['content']);
    }

    /**
     * Prompt content as the model reads it: a plain string as it is, a list of
     * text blocks one after another.
     *
     * @param  string|list<array{type: string, text: string}>  $content
     */
    private function joinedText(string|array $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        return implode("\n\n", array_map(fn (array $block): string => $block['text'], $content));
    }
}
