<?php

namespace App\Concerns;

use Anthropic\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Asks Claude a question it may already have been asked.
 *
 * The same card, the same plan, asked again word for word, gets the same answer
 * back — so the answer is kept for a week and handed out again instead of being
 * paid for a second time. Anything at all that changes in the request, a prompt,
 * a schema, the model itself, is a different question and goes to the API.
 *
 * A caller that wants the model's own reading of a question it has already
 * answered — someone working on the prompt, most of all — asks for it with
 * {@see withoutClaudeCache()}.
 */
trait CachesClaudeMessages
{
    /**
     * Whether an answer already paid for may stand in for a new one. Turned off
     * by {@see withoutClaudeCache()} for a run that wants the model's own
     * reading of a question it has been asked before.
     */
    protected bool $claudeCacheEnabled = true;

    /**
     * Ask the model itself from here on, however many times the same question
     * has already been answered. The answers that come back are still kept, so
     * a stale reading is replaced rather than merely passed over.
     */
    public function withoutClaudeCache(): static
    {
        $this->claudeCacheEnabled = false;

        return $this;
    }

    /**
     * The model's answer to exactly these parameters, from the cache when it has
     * been asked before, from the API otherwise. An empty answer is not kept:
     * nothing is gained by remembering silence for a week.
     *
     * @param  array<string, mixed>  $params  Named arguments for `messages->create()`.
     */
    protected function askClaude(Client $client, array $params): string
    {
        $key = 'claude:message:'.hash('sha256', serialize($params));

        $cached = $this->claudeCacheEnabled ? Cache::get($key) : null;

        if (is_string($cached)) {
            Log::info('Answered from the Claude cache', ['key' => $key]);

            return $cached;
        }

        $message = $client->messages->create(...$params);

        // @phpstan-ignore-next-line property.notFound
        $answer = trim((string) $message->content[0]->text);

        if ($answer !== '') {
            Cache::put($key, $answer, config('services.anthropic.cache_ttl'));
        }

        return $answer;
    }

    /**
     * The client this service talks to the API through, built from the house's
     * own key.
     *
     * Refused outright in tests. The SDK carries its own HTTP stack, so
     * `Http::preventStrayRequests()` — which `Tests\TestCase` arms for
     * everything else — never sees a call made through it, and a test that
     * built one here would be making a real, paid, differently-worded-every-time
     * request. A test exercising a service for real hands it a client of its own
     * with a canned transport instead, the way
     * `Tests\Concerns\AnswersAsTheExtractionModel` does; a test that only needs
     * the service to have been asked mocks the service.
     */
    protected function claudeClient(): Client
    {
        if (app()->runningUnitTests()) {
            throw new RuntimeException(sprintf(
                '%s tried to build a real Claude client in a test. Hand it a client with a '
                .'canned transport (see Tests\Concerns\AnswersAsTheExtractionModel) or mock '
                .'the service itself.',
                static::class,
            ));
        }

        return new Client(config('services.anthropic.key'));
    }
}
