<?php

namespace App\Concerns;

use Anthropic\Client;
use Illuminate\Support\Collection;
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
     * A summary of what the model thought through before its last answer, or
     * null when it answered without thinking. Kept with the answer in the
     * cache, so a reading handed out again comes with the thinking behind it.
     */
    protected ?string $claudeThinking = null;

    /**
     * What the model thought through before the answer {@see askClaude()} last
     * returned, summarised by the API, or null when it went straight to the
     * answer — at low effort, most of the time.
     */
    public function lastClaudeThinking(): ?string
    {
        return $this->claudeThinking;
    }

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
     * Every question is asked the house's way, whatever the caller passes — see
     * {@see withHouseDefaults()}.
     *
     * @param  array<string, mixed>  $params  Named arguments for `beta->messages->create()`.
     * @param  (callable(string): bool)|null  $keep  Whether an answer is sound enough to keep.
     */
    protected function askClaude(Client $client, array $params, ?callable $keep = null): string
    {
        $params = $this->withHouseDefaults($params);
        $key = 'claude:message:'.hash('sha256', serialize($params));
        $this->claudeThinking = null;

        $cached = $this->claudeCacheEnabled ? Cache::get($key) : null;

        if (is_array($cached) && is_string($cached['answer'] ?? null)) {
            Log::info('Answered from the Claude cache', ['key' => $key]);

            $this->claudeThinking = is_string($cached['thinking'] ?? null) ? $cached['thinking'] : null;

            return $cached['answer'];
        }

        $message = $client->beta->messages->create(...$params);

        // What the API's own prompt cache did for this call: a run whose reads
        // stay at zero is a prefix that changes between calls, or one too
        // short to be cached at all — neither of which raises an error. The
        // model is the one that answered, which a fallback can make another.
        Log::info('Claude usage', [
            'service' => static::class,
            'model' => $message->model,
            'stop_reason' => $message->stopReason,
            'input_tokens' => $message->usage->inputTokens,
            'cache_creation_input_tokens' => $message->usage->cacheCreationInputTokens ?? 0,
            'cache_read_input_tokens' => $message->usage->cacheReadInputTokens ?? 0,
            'output_tokens' => $message->usage->outputTokens,
        ]);

        if ($message->stopReason === 'refusal') {
            // Comes back as an ordinary answer with nothing in it, so it is
            // said here, once, rather than left to read as a model that had
            // nothing to say. Not cached: the answer is empty.
            Log::warning('Claude declined to answer', [
                'service' => static::class,
                'model' => $message->model,
                'category' => $message->stopDetails?->category,
                'explanation' => $message->stopDetails?->explanation,
            ]);
        }

        $answer = self::answerText($message->content);
        $this->claudeThinking = self::thinkingText($message->content);

        if ($answer !== '' && ($keep === null || $keep($answer))) {
            Cache::put(
                $key,
                ['answer' => $answer, 'thinking' => $this->claudeThinking],
                config('services.anthropic.cache_ttl'),
            );
        }

        return $answer;
    }

    /**
     * The parameters every call is made with, whichever service makes it.
     *
     * - **Effort.** Sonnet 5.5 refuses `thinking: disabled`. The model is left
     *   to think when a question needs it, and the effort level in
     *   `services.anthropic.effort` keeps that short. Set beside whatever else
     *   the caller asks of the answer's shape.
     * - **Thinking shown.** Left to itself the API keeps the model's thinking
     *   back and returns its blocks empty; `"summarized"` returns a summary of
     *   it instead, for {@see lastClaudeThinking()}. The thinking itself, and
     *   what it costs, is the same either way.
     * - **Fallback.** A model that declines a question for policy reasons
     *   ends the call with nothing in it. `"default"` has the API ask the
     *   model's own fallback in its place, in the same call, so a declined
     *   reading still comes back answered.
     *
     * Applied before the answer cache's key is worked out, so a change here is
     * a different question rather than a cached answer to the old one.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function withHouseDefaults(array $params): array
    {
        /** @var array<string, mixed> $outputConfig */
        $outputConfig = $params['outputConfig'] ?? [];

        return [
            ...$params,
            'outputConfig' => ['effort' => config('services.anthropic.effort'), ...$outputConfig],
            'thinking' => ['type' => 'adaptive', 'display' => 'summarized'],
            'fallbacks' => 'default',
            'betas' => ['server-side-fallback-2026-07-01'],
        ];
    }

    /**
     * What the model said, from the text blocks of its answer.
     *
     * Not just the first block: with thinking on, the answer can open with the
     * model's thinking, and a fallback adds a block marking where one model
     * handed over to another. Neither is part of the answer.
     *
     * @param  list<object>  $content
     */
    protected static function answerText(array $content): string
    {
        // Joined as they came: an answer split over blocks is one piece of
        // text, JSON included.
        return trim(self::fieldsOf($content, 'text', 'text')->implode(''));
    }

    /**
     * The summary of the model's thinking, from the thinking blocks of its
     * answer, or null when there are none or they came back empty.
     *
     * @param  list<object>  $content
     */
    protected static function thinkingText(array $content): ?string
    {
        $thinking = self::fieldsOf($content, 'thinking', 'thinking')
            ->map(fn (string $part): string => trim($part))
            ->filter(fn (string $part): bool => $part !== '')
            ->implode("\n\n");

        return $thinking === '' ? null : $thinking;
    }

    /**
     * The given field of every content block of the given type, in order.
     *
     * @param  list<object>  $content
     * @return Collection<int, string>
     */
    private static function fieldsOf(array $content, string $type, string $field): Collection
    {
        return collect($content)
            ->filter(fn (object $block): bool => ($block->type ?? null) === $type && is_string($block->{$field} ?? null))
            ->map(fn (object $block): string => $block->{$field})
            ->values();
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
