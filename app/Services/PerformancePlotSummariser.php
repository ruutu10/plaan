<?php

namespace App\Services;

use Anthropic\Client;
use App\Concerns\CachesClaudeMessages;
use App\Data\PlotSummary;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Writes up what happened on stage from a recording's speech-to-text subtitles.
 *
 * The subtitles are noisy — misheard words, merged lines, text nobody said —
 * so the reading is left to Claude, told as much and handed what this app knows
 * about the night: the format, and who was on stage, which is most of what it
 * needs to put a misheard name right. The answer is held to a JSON schema with
 * room to say the transcript is unreadable, so a garbled track is reported as
 * such rather than turned into a plot nobody played.
 */
class PerformancePlotSummariser
{
    use CachesClaudeMessages;

    /**
     * A summary is a few paragraphs; this is room for them with margin, far
     * under the house default sized for reading whole Planka cards.
     */
    private const MAX_TOKENS = 5000;

    /**
     * Enough tags to say what a night is about; more and they stop helping
     * anybody find it in the library.
     */
    private const MAX_TAGS = 8;

    /**
     * A tag is a word or two. Anything longer is a sentence the model put in
     * the wrong field.
     */
    private const MAX_TAG_LENGTH = 40;

    public function __construct(protected ?Client $client = null)
    {
        //
    }

    /**
     * Whether summaries can be written here at all: subtitles to read from the
     * library, and a model to read them with.
     */
    public static function isConfigured(): bool
    {
        return JellyfinClient::isConfigured() && filled(config('services.anthropic.key'));
    }

    /**
     * Summarise the night from its subtitles, given as SRT.
     *
     * @throws RuntimeException when the model's answer is not the JSON asked for
     */
    public function summarise(Performance $performance, string $srt): PlotSummary
    {
        $performance->loadMissing(['format', 'onStage']);

        $details = array_values(array_filter([
            'Formaat: '.$performance->displayName(),
            'Kuupäev: '.$performance->startDate(),
            filled($performance->performerName()) ? 'Esineja: '.$performance->performerName() : null,
            filled($performance->format->description) ? "Formaadi kirjeldus:\n".$performance->format->description : null,
        ]));

        return $this->summariseNight(
            $details,
            array_values($performance->onStage->map(fn (User $member): string => $member->name)->all()),
            $srt,
            ['performance_id' => $performance->getKey()],
        );
    }

    /**
     * Summarise a night described by plain lines rather than a model — for a
     * library episode this app holds no record of.
     *
     * @param  list<string>  $details  "Label: value" lines about the night, in Estonian.
     * @param  list<string>  $onStage  Names of the people on stage.
     * @param  array<string, mixed>  $logContext
     *
     * @throws RuntimeException when the model's answer is not the JSON asked for
     */
    public function summariseNight(array $details, array $onStage, string $srt, array $logContext = []): PlotSummary
    {
        $transcript = self::transcriptFrom($srt);

        if ($transcript === '') {
            return new PlotSummary(null, 'Subtiitrites pole teksti.');
        }

        $userPrompt = $this->buildUserPrompt($details, $onStage, $transcript);
        $systemPrompt = view('performance.ai-plot-summary-prompt')->render();

        $startedAt = microtime(true);

        $answer = $this->askClaude($this->client(), [
            'maxTokens' => self::MAX_TOKENS,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'model' => config('services.anthropic.model'),
            'outputConfig' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $this->responseSchema(),
                ],
            ],
            'system' => $systemPrompt,
            'thinking' => ['type' => 'disabled'],
        ], keep: fn (string $answer): bool => self::isWhole($answer));

        $decoded = json_decode($answer, true);

        if (! is_array($decoded)) {
            // Rare, and not reproducible on demand, so the answer itself is
            // the only evidence of what went wrong. Not cached — see isWhole().
            Log::warning('The plot summary model answered with something other than JSON', [
                ...$logContext,
                'json_error' => json_last_error_msg(),
                'answer_chars' => mb_strlen($answer),
                'answer_start' => mb_substr($answer, 0, 500),
            ]);

            throw new RuntimeException('The plot summary model answered with something other than JSON.');
        }

        // Thrown rather than kept, and left out of the cache, so the queue's
        // retry asks the model afresh — see isWhole().
        if (! self::isWhole($answer)) {
            throw new RuntimeException('The plot summary model answered with a summary cut off mid-sentence.');
        }

        $summary = is_string($decoded['summary'] ?? null) ? self::tidy($decoded['summary']) : '';
        $usable = ($decoded['usable'] ?? false) === true && $summary !== '';

        $reading = $usable
            ? new PlotSummary($summary, tags: self::tagsFrom($decoded['tags'] ?? []))
            : new PlotSummary(null, is_string($decoded['reason'] ?? null) ? $decoded['reason'] : null);

        Log::info('Summarised a performance from its subtitles', [
            ...$logContext,
            'usable' => $usable,
            'transcript_chars' => mb_strlen($transcript),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        // The text itself, kept out of the info line above so production logs
        // stay short, but there to review when the level allows it.
        Log::debug('Plot summary content', [
            ...$logContext,
            'summary' => $reading->summary,
            'tags' => $reading->tags,
            'reason' => $reading->reason,
        ]);

        return $reading;
    }

    /**
     * Whether an answer is well-formed and, when it holds a summary, the
     * summary ends where a sentence does.
     *
     * The answer is held to a JSON schema, and a straight quote the model
     * writes inside the summary unescaped — `ettepanekust "kalapüük"` — reads
     * to that grammar as the end of the string: the rest of the summary is
     * dropped and the answer carries on, perfectly valid, with the tags. The
     * prompt asks for Estonian quotes („…“) to keep this from happening; this
     * is what catches it when it happens anyway.
     */
    public static function isWhole(string $answer): bool
    {
        $decoded = json_decode($answer, true);

        if (! is_array($decoded)) {
            return false;
        }

        $summary = is_string($decoded['summary'] ?? null) ? self::tidy($decoded['summary']) : '';

        return $summary === '' || preg_match('/[.!?…"“”»)]$/u', $summary) === 1;
    }

    /**
     * The words of an SRT file, one cue to a line, in the order spoken.
     *
     * Cue numbers and timestamps go: the model is asked what happened, not
     * when, and they are a third of the tokens. A cue repeating the one before
     * it — speech-to-text does this over a held note or a long laugh — is kept
     * once.
     */
    public static function transcriptFrom(string $srt): string
    {
        $srt = preg_replace('/^\xEF\xBB\xBF/', '', $srt) ?? $srt;
        $blocks = preg_split('/\R\s*\R/u', trim(str_replace("\r\n", "\n", $srt))) ?: [];

        $lines = [];

        foreach ($blocks as $block) {
            $text = [];

            foreach (preg_split('/\R/u', $block) ?: [] as $line) {
                $line = trim($line);

                if ($line === '' || preg_match('/^\d+$/', $line) === 1 || str_contains($line, '-->')) {
                    continue;
                }

                // Styling tags are the renderer's, not anybody's words.
                $text[] = trim(strip_tags($line));
            }

            $cue = trim(implode(' ', array_filter($text)));

            if ($cue !== '' && end($lines) !== $cue) {
                $lines[] = $cue;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * A summary as plain paragraphs: any Unicode space (the model now and then
     * emits a full-width one) read as an ordinary one, no trailing blanks, and
     * no more than one empty line between paragraphs.
     */
    public static function tidy(string $summary): string
    {
        $summary = preg_replace('/[^\S\n]+/u', ' ', str_replace("\r\n", "\n", $summary)) ?? $summary;
        $summary = preg_replace('/ *\n */u', "\n", $summary) ?? $summary;
        $summary = preg_replace('/\n{3,}/u', "\n\n", $summary) ?? $summary;

        return trim($summary);
    }

    /**
     * The model's tags as the library keeps them: lowercase, trimmed, each once,
     * short enough to read as a tag, and no more than {@see MAX_TAGS} of them.
     * Anything that is not a non-empty string is dropped.
     *
     * @return list<string>
     */
    public static function tagsFrom(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $clean = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $tag = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $tag) ?? $tag));

            if ($tag !== '' && mb_strlen($tag) <= self::MAX_TAG_LENGTH && ! in_array($tag, $clean, true)) {
                $clean[] = $tag;
            }
        }

        return array_slice($clean, 0, self::MAX_TAGS);
    }

    /**
     * The night and its transcript, in the sections the system prompt names.
     *
     * @param  list<string>  $details
     * @param  list<string>  $onStage
     */
    protected function buildUserPrompt(array $details, array $onStage, string $transcript): string
    {
        $onStage = implode("\n", array_map(fn (string $name): string => '- '.$name, $onStage));

        return implode("\n\n", [
            "# Etendus\n".implode("\n", $details),
            "# Laval\n".($onStage === '' ? 'teadmata' : $onStage),
            "# Subtiitrid\n".$transcript,
        ]);
    }

    /**
     * The shape the answer is held to.
     *
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'usable' => [
                    'type' => 'boolean',
                    'description' => 'Whether a trustworthy account of the performance could be read out of the subtitles.',
                ],
                'summary' => [
                    'anyOf' => [['type' => 'string'], ['type' => 'null']],
                    'description' => 'A synopsis of the central story in Estonian, one paragraph of 4–6 short sentences, at most 70 words; null when not usable.',
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Thematic tags for the performance in Estonian, lowercase, one or two words each (e.g. "armastus", "kalapüük", "laul"); empty when not usable.',
                ],
                'reason' => [
                    'anyOf' => [['type' => 'string'], ['type' => 'null']],
                    'description' => 'Why the subtitles were not usable, in Estonian; null when they were.',
                ],
            ],
            'required' => ['usable', 'summary', 'tags', 'reason'],
            'additionalProperties' => false,
        ];
    }

    protected function client(): Client
    {
        return $this->client ??= $this->claudeClient();
    }
}
