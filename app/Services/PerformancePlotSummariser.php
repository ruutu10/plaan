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
    private const MAX_TOKENS = 4000;

    public function __construct(protected ?Client $client = null)
    {
        //
    }

    /**
     * Summarise the night from its subtitles, given as SRT.
     *
     * @throws RuntimeException when the model's answer is not the JSON asked for
     */
    public function summarise(Performance $performance, string $srt): PlotSummary
    {
        $transcript = self::transcriptFrom($srt);

        if ($transcript === '') {
            return new PlotSummary(null, 'Subtiitrites pole teksti.');
        }

        $startedAt = microtime(true);

        $answer = $this->askClaude($this->client(), [
            'maxTokens' => self::MAX_TOKENS,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $this->buildUserPrompt($performance, $transcript),
                ],
            ],
            'model' => config('services.anthropic.model'),
            'outputConfig' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $this->responseSchema(),
                ],
            ],
            'system' => view('performance.ai-plot-summary-prompt')->render(),
            'thinking' => ['type' => 'disabled'],
        ]);

        $decoded = json_decode($answer, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The plot summary model answered with something other than JSON.');
        }

        $summary = is_string($decoded['summary'] ?? null) ? self::tidy($decoded['summary']) : '';
        $usable = ($decoded['usable'] ?? false) === true && $summary !== '';

        $reading = $usable
            ? new PlotSummary($summary)
            : new PlotSummary(null, is_string($decoded['reason'] ?? null) ? $decoded['reason'] : null);

        Log::info('Summarised a performance from its subtitles', [
            'performance_id' => $performance->getKey(),
            'usable' => $usable,
            'transcript_chars' => mb_strlen($transcript),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $reading;
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
     * The night and its transcript, in the sections the system prompt names.
     */
    protected function buildUserPrompt(Performance $performance, string $transcript): string
    {
        $performance->loadMissing(['format', 'onStage']);

        $details = array_filter([
            'Formaat: '.$performance->displayName(),
            'Kuupäev: '.$performance->startDate(),
            filled($performance->performerName()) ? 'Esineja: '.$performance->performerName() : null,
            filled($performance->format->description) ? "Formaadi kirjeldus:\n".$performance->format->description : null,
        ]);

        $onStage = $performance->onStage
            ->map(fn (User $member): string => '- '.$member->name)
            ->implode("\n");

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
                    'description' => 'The plot summary in Estonian, 2–4 plain paragraphs; null when not usable.',
                ],
                'reason' => [
                    'anyOf' => [['type' => 'string'], ['type' => 'null']],
                    'description' => 'Why the subtitles were not usable, in Estonian; null when they were.',
                ],
            ],
            'required' => ['usable', 'summary', 'reason'],
            'additionalProperties' => false,
        ];
    }

    protected function client(): Client
    {
        return $this->client ??= $this->claudeClient();
    }
}
