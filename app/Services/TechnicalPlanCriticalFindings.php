<?php

namespace App\Services;

use Anthropic\Client;
use App\Concerns\CachesClaudeMessages;
use App\Listeners\ReviewSubmittedPlanWithAi;
use Illuminate\Support\Facades\Log;

/**
 * Sifts an AI review of a technical plan down to the things that would stop the
 * show.
 *
 * A review written for a performer to read is generous by design — it praises
 * what works, asks for precision where precision would help, and offers ideas.
 * None of that is worth interrupting somebody over. This second pass is given
 * the review and nothing else, and asked one question: is there a contradiction
 * that makes the plan unplayable, or a sound a scene calls for that the
 * technician has no way to play? Everything else is dropped.
 *
 * Most reviews yield nothing here, and that is the point: what comes back is
 * only ever worth putting in front of the performer as something to fix.
 *
 * @see ReviewSubmittedPlanWithAi
 */
class TechnicalPlanCriticalFindings
{
    use CachesClaudeMessages;

    public function __construct(protected ?Client $client = null)
    {
        //
    }

    /**
     * The show-stoppers in a review, each already worded as something the
     * performer should go and fix. Empty — the usual answer — when the review
     * describes a plan that can be played as it stands.
     *
     * @param  string  $reviewMarkdown  The first pass's feedback in its own words.
     * @return list<string>
     */
    public function findIn(string $reviewMarkdown): array
    {
        if (trim($reviewMarkdown) === '') {
            return [];
        }

        $startedAt = microtime(true);

        $aiResponse = $this->askClaude($this->client(), [
            'maxTokens' => config('services.anthropic.max_tokens'),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $reviewMarkdown,
                ],
            ],
            'model' => config('services.anthropic.model'),
            'outputConfig' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $this->responseSchema(),
                ],
            ],
            'system' => $this->buildSystemPrompt(),
            'thinking' => ['type' => 'disabled'],
        ]);

        Log::debug('AI sifting of a plan review', ['aiOutput' => $aiResponse]);

        $findings = $this->parse($aiResponse);

        // How often the second pass finds anything at all is the number worth
        // watching: a run that flags every plan is a prompt that has stopped
        // sifting, not a season of bad plans.
        Log::info('Sifted an AI review for show-stoppers', [
            'findings' => count($findings),
            'reasoningNotes' => $this->readReasoningNotes($aiResponse),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $findings;
    }

    /**
     * The findings the model returned, less anything that is not a line of text
     * somebody could act on.
     *
     * @return list<string>
     */
    protected function parse(string $aiResponse): array
    {
        $decoded = json_decode($aiResponse, true);
        $findings = is_array($decoded) ? ($decoded['criticalFindings'] ?? null) : null;

        if (! is_array($findings)) {
            // Told apart from "nothing to report", which is the same empty
            // list: one is the answer, the other is no answer at all.
            Log::warning('The second pass over an AI review came back unreadable', [
                'aiOutput' => $aiResponse,
            ]);

            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $finding): string => trim((string) (is_scalar($finding) ? $finding : '')),
                $findings,
            ),
            fn (string $finding): bool => $finding !== '',
        ));
    }

    /**
     * Why the sifting went the way it did, in the model's own words. Logged for
     * whoever has to work out later why a plan was — or was not — flagged;
     * never shown to anyone using the app.
     *
     * @return list<string>
     */
    protected function readReasoningNotes(string $aiResponse): array
    {
        $decoded = json_decode($aiResponse, true);
        $notes = is_array($decoded) ? ($decoded['reasoningNotes'] ?? null) : null;

        if (! is_array($notes)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $note): string => trim((string) (is_scalar($note) ? $note : '')), $notes),
            fn (string $note): bool => $note !== '',
        ));
    }

    /**
     * The shape the answer must take, enforced by the API rather than hoped for.
     *
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'criticalFindings' => [
                    'type' => 'array',
                    'description' => 'Ainult need ülevaatuse leiud, mis takistavad etenduse mängimist: mängimatuks tegev vastuolu või stseeni heli, mida tehnikul pole kuskilt mängida. Iga leid ühe lühikese eestikeelse lausena, esinejale sina-vormis, öeldes mis on puudu ja mida teha. Tähtsuse järjekorras. Tühi massiiv on tavaline ja korrektne vastus.',
                    'items' => ['type' => 'string'],
                ],
                'reasoningNotes' => [
                    'type' => 'array',
                    'description' => 'Lühikesed märkmed selle kohta, miks iga leid sisse võeti või välja jäeti, arendajale. Ei näidata kellelegi rakenduses.',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['criticalFindings', 'reasoningNotes'],
            'additionalProperties' => false,
        ];
    }

    /**
     * What counts as a show-stopper, and what to let past.
     */
    protected function buildSystemPrompt(): string
    {
        return view('technical-plan.ai-critical-findings-prompt')->render();
    }

    /**
     * The API client, built on first use so an unconfigured key only bites the
     * code paths that actually call out.
     */
    protected function client(): Client
    {
        return $this->client ??= new Client(config('services.anthropic.key'));
    }
}
