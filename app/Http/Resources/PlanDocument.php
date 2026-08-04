<?php

namespace App\Http\Resources;

use App\Enums\TechnicalPlanStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The plan as a *finished document*: every value already rendered to the string
 * the reader sees. The wizard's review page, the printout and the mail all show
 * the same document, so the rules that turn stored values into readable
 * ones — the em dash for a blank field, "Jah — detail", the smoke wording, file
 * sizes — live here rather than in each template.
 *
 * The wizard cannot use this directly: its review step renders a plan that has
 * not been saved yet, so the same rules are mirrored in
 * `resources/js/components/technical-plan/presentPlan.ts`. The two are held
 * together by `tests/fixtures/plan-document.json`, which both are asserted
 * against — a rule changed on one side alone fails a test.
 *
 * Input is the array shape produced by {@see TechnicalPlan}, which is also the
 * frontend's `Plan` shape, so both presenters read the very same input.
 *
 * @property-read array<string, mixed> $resource
 */
class PlanDocument extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * Who to name as the plan's contact. Not part of the plan itself: the mail
     * knows the author, the wizard knows the signed-in user.
     */
    private string $contact = '';

    public function withContact(?string $email): static
    {
        $this->contact = (string) $email;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;
        $meta = $plan['meta'];
        $sound = $plan['sound'];
        $equipment = $plan['equipment'];

        return [
            'token' => $plan['token'] ?? null,
            'statusLabel' => self::statusLabel($plan['status'] ?? null),

            'formatName' => self::dash($meta['formatName'] ?? null),
            'performer' => self::dash($meta['performer'] ?? null),
            'contact' => self::dash($this->contact),
            'performanceDate' => self::dash($meta['performanceDate'] ?? null),
            'startTime' => self::dash($meta['startTime'] ?? null),
            'durationLabel' => self::duration($meta['duration'] ?? null),
            'description' => self::dash($meta['description'] ?? null),

            'micsSummary' => self::answer($sound['micsMode'] ?? null, $sound['micsDetail'] ?? null),
            'musicianSummary' => self::answer($sound['musicianMode'] ?? null, $sound['musicianDetail'] ?? null),

            'scenes' => self::scenes($plan['scenes'] ?? []),

            'equipmentItems' => self::equipmentItems($equipment['items'] ?? []),
            'smokeSummary' => self::smoke($equipment['smoke'] ?? null),
            'suggestionsLine' => self::suggestions($equipment['suggestions'] ?? null, $equipment['suggestNote'] ?? null),

            'notes' => self::dash($plan['extra']['notes'] ?? null),
            'files' => self::files($plan['extra']['files'] ?? []),
        ];
    }

    /**
     * A value the plan may have left empty, shown as an em dash.
     */
    public static function dash(mixed $value): string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : '—';
    }

    /**
     * A "yes/no" answer and its free-text detail, on one line. Used for the two
     * sound questions and for the technician's offer of suggestions.
     */
    public static function answer(mixed $mode, mixed $detail): string
    {
        if ($mode !== 'yes') {
            return 'Ei';
        }

        $text = trim((string) $detail);

        return $text !== '' ? 'Jah — '.$text : 'Jah';
    }

    /**
     * Whether the performer wants the technician's suggestions, and what they
     * wrote alongside. Unlike {@see answer()} the note is kept even on a "no":
     * someone declining suggestions and then explaining why is telling the
     * technician something worth reading.
     */
    public static function suggestions(mixed $mode, mixed $note): string
    {
        $text = trim((string) $note);

        return ($mode === 'yes' ? 'Jah' : 'Ei').($text !== '' ? ' — '.$text : '');
    }

    public static function duration(mixed $minutes): string
    {
        return $minutes ? $minutes.' min' : '—';
    }

    public static function smoke(mixed $smoke): string
    {
        return match ($smoke) {
            'no' => 'Ei tohi',
            'yes' => 'Jah',
            default => 'Jah, kuid minimaalselt',
        };
    }

    /**
     * The plan's status in the reader's words. Unknown values fall back to the
     * draft label, which is what an unsaved plan in the wizard is.
     */
    public static function statusLabel(mixed $status): string
    {
        return (TechnicalPlanStatus::tryFrom((string) $status) ?? TechnicalPlanStatus::Draft)->label();
    }

    /**
     * A file size both the mail and the browser render identically. Laravel's
     * `Number::fileSize()` formats through the app locale, which would put a
     * comma in the mail and a full stop on screen for the same file.
     */
    public static function fileSize(mixed $bytes): string
    {
        $bytes = (int) $bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, 1, '.', '').' '.$units[$unit];
    }

    /**
     * The scene rows, numbered as the reader counts them: a cue called out as
     * "stseen 4" is the fourth row in the mail, on the printout and in the
     * technician's playback view alike.
     *
     * @param  array<int, array<string, mixed>>  $scenes
     * @return array<int, array<string, mixed>>
     */
    private static function scenes(array $scenes): array
    {
        return array_map(fn (array $scene, int $index): array => [
            'num' => $index + 1,
            'name' => self::dash($scene['name'] ?? null),
            'light' => self::dash($scene['light'] ?? null),
            'soundFile' => self::soundFile($scene['soundFile'] ?? null),
            'soundUrl' => trim((string) ($scene['soundUrl'] ?? '')),
            // The file and the link get their own lines above this, so the text
            // is only stood in for by a dash when the scene has no sound at all.
            'soundText' => self::soundText($scene),
            'notes' => self::dash($scene['notes'] ?? null),
        ], $scenes, array_keys($scenes));
    }

    /**
     * @param  array<string, mixed>  $scene
     */
    private static function soundText(array $scene): string
    {
        $text = trim((string) ($scene['sound'] ?? ''));

        if ($text !== '') {
            return $text;
        }

        $hasOtherSound = ($scene['soundFile'] ?? null) || trim((string) ($scene['soundUrl'] ?? '')) !== '';

        return $hasOtherSound ? '' : '—';
    }

    /**
     * @param  array<string, mixed>|null  $file
     * @return array<string, mixed>|null
     */
    private static function soundFile(?array $file): ?array
    {
        return $file ? self::file($file) : null;
    }

    /**
     * One file handle, named and sized. The same shape for a scene's sound cue
     * and for a plan attachment, so a template renders either the same way.
     *
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private static function file(array $file): array
    {
        return [
            'name' => (string) ($file['name'] ?? ''),
            'sizeLabel' => self::fileSize($file['size'] ?? 0),
            'url' => $file['url'] ?? null,
            'downloadUrl' => $file['downloadUrl'] ?? null,
        ];
    }

    /**
     * Rows the performer left entirely blank are not part of the document.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, string>>
     */
    private static function equipmentItems(array $items): array
    {
        $filled = array_filter(
            $items,
            fn (array $item): bool => trim((string) ($item['name'] ?? '')) !== ''
                || trim((string) ($item['use'] ?? '')) !== '',
        );

        return array_values(array_map(fn (array $item): array => [
            'name' => self::dash($item['name'] ?? null),
            'use' => self::dash($item['use'] ?? null),
        ], $filled));
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array<string, mixed>>
     */
    private static function files(array $files): array
    {
        return array_values(array_map(self::file(...), $files));
    }
}
