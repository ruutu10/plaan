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
 *
 * Separately, a question that only starts the same as the last one can mark
 * that start with {@see cachedPromptBlock()}, so the API bills the repeated
 * part as a cache read; what each call read and wrote is logged.
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
     * An answer the caller's `$keep` check turns down is handed back but not
     * kept either: a malformed reading cached for weeks would come straight
     * back on every retry.
     *
     * @param  array<string, mixed>  $params  Named arguments for `messages->create()`.
     * @param  (callable(string): bool)|null  $keep  Whether an answer is sound enough to keep.
     */
    protected function askClaude(Client $client, array $params, ?callable $keep = null): string
    {
        $key = 'claude:message:'.hash('sha256', serialize($params));

        $cached = $this->claudeCacheEnabled ? Cache::get($key) : null;

        if (is_string($cached)) {
            Log::info('Answered from the Claude cache', ['key' => $key]);

            return $cached;
        }

        $message = $client->messages->create(...$params);

        // What the API's own prompt cache did for this call: a run whose reads
        // stay at zero is a prefix that changes between calls, or one too
        // short to be cached at all — neither of which raises an error.
        Log::info('Claude usage', [
            'service' => static::class,
            'model' => $params['model'] ?? null,
            'input_tokens' => $message->usage->inputTokens,
            'cache_creation_input_tokens' => $message->usage->cacheCreationInputTokens ?? 0,
            'cache_read_input_tokens' => $message->usage->cacheReadInputTokens ?? 0,
            'output_tokens' => $message->usage->outputTokens,
        ]);

        // @phpstan-ignore-next-line property.notFound
        $answer = trim((string) $message->content[0]->text);

        if ($answer !== '' && ($keep === null || $keep($answer))) {
            Cache::put($key, $answer, config('services.anthropic.cache_ttl'));
        }

        return $answer;
    }

    /**
     * A piece of prompt text the API is asked to keep a copy of, so the next
     * call that begins with the same text reads it back at a tenth of the
     * price instead of paying for it in full.
     *
     * This is the API's own prompt cache, not the answer cache above: it pays
     * off when the start of a prompt repeats — the system prompt, the house's
     * lists — while the rest of it differs. Everything up to and including the
     * block is the cached prefix, so a block only belongs here when nothing
     * before it changes from one call to the next. The copy lasts five minutes
     * from its last use, which is what a run of calls made one after another
     * keeps alive.
     *
     * @return array{type: 'text', text: string, cacheControl: array{type: 'ephemeral'}}
     */
    protected function cachedPromptBlock(string $text): array
    {
        return [
            'type' => 'text',
            'text' => $text,
            'cacheControl' => ['type' => 'ephemeral'],
        ];
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
