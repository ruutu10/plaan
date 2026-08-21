<?php

namespace App\Concerns;

use Anthropic\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Asks Claude a question it may already have been asked.
 *
 * The same card, the same plan, asked again word for word, gets the same answer
 * back — so the answer is kept for a week and handed out again instead of being
 * paid for a second time. Anything at all that changes in the request, a prompt,
 * a schema, the model itself, is a different question and goes to the API.
 */
trait CachesClaudeMessages
{
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

        $cached = Cache::get($key);

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
}
