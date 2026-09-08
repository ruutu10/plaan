<?php

namespace App\Http\Resources;

use App\Actions\StagePlanCopy;
use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The nested payload consumed by the frontend wizard, mirroring the
 * TypeScript `Plan` shape: a flat `meta` block plus the uploaded file handles.
 *
 * @property-read TechnicalPlanModel $resource
 */
class TechnicalPlan extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * File handles to report in place of the plan's own — the staged copies
     * made by {@see StagePlanCopy} when the plan is being opened
     * as the basis for a new one.
     *
     * @var array{files: Collection<int, Media>, sceneSoundFiles: Collection<string, Media>}|null
     */
    private ?array $stagedCopy = null;

    /**
     * Serialise the plan as the basis for a *new* plan, reporting the staged
     * copies of its files rather than the plan's own, so submitting the copy
     * carries the files over without affecting this plan.
     *
     * @param  array{files: Collection<int, Media>, sceneSoundFiles: Collection<string, Media>}  $staged
     */
    public function withStagedCopy(array $staged): static
    {
        $this->stagedCopy = $staged;

        return $this;
    }

    /**
     * Transform the plan into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;

        return [
            'token' => $plan->token,
            'status' => $plan->status->value,
            'submittedAt' => $plan->submitted_at?->toIso8601String(),
            'authorEmail' => self::authorEmail($plan, $request),
            'meta' => [
                'performanceId' => $plan->performance_id,
                'performer' => $plan->performance?->performerName() ?? '',
                'formatName' => $plan->performance?->format->name ?? '',
                'performanceDate' => $plan->performance?->startDate() ?? '',
                'startTime' => $plan->performance?->startTime() ?? '',
                // No nullsafe, unlike its neighbours: this one reads a nullable
                // property, so the coalesce already covers a plan with no night.
                'location' => $plan->performance->location ?? '',
                'duration' => $plan->performance?->duration,
                'description' => $plan->performance?->format->description ?? '',
            ],
            'sound' => $this->sound(),
            'scenes' => PlanScene::forPlan($plan, $request, $this->stagedCopy['sceneSoundFiles'] ?? null),
            'equipment' => $this->equipment(),
            'extra' => [
                'notes' => self::text($plan->extra['notes'] ?? null),
                'files' => Attachment::collection(
                    $this->stagedCopy['files'] ?? $plan->attachments()
                )->resolve($request),
            ],
        ];
    }

    /**
     * Who to name as the plan's contact: the person who handed it in, not
     * whoever is reading it. A plan is regularly opened by somebody else — a
     * technician, a team-mate, the crew — and the document has to keep naming
     * its author; see {@see PlanDocument::withContact()}, which the mail feeds
     * from the very same place.
     *
     * A guest gets nothing: the share link is open to anyone holding it, and an
     * email address is not what a link hands out.
     */
    private static function authorEmail(TechnicalPlanModel $plan, Request $request): ?string
    {
        return $request->user() !== null ? $plan->user?->email : null;
    }

    /**
     * A stored JSON value the wizard expects as a string. Every wizard field is
     * optional, so a plan can hold a `null` (or nothing at all) where the
     * frontend's `Plan` shape promises text — hand it the empty string instead.
     */
    public static function text(mixed $value, string $default = ''): string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    /**
     * How long a scene entry's interval lasts, in whole minutes, or zero when
     * the entry is an ordinary scene. The one place the stored value is judged,
     * so the wizard's document and the mail draw the line in the same place;
     * `intermissionMinutes()` in
     * `resources/js/components/technical-plan/plan.ts` mirrors it client-side.
     */
    public static function intermission(mixed $value): int
    {
        $minutes = is_numeric($value) ? (int) $value : 0;

        return max($minutes, 0);
    }

    /**
     * The interval as every reader is shown it, minutes included. Mirrored by
     * `intermissionLabel()` in the wizard's own `plan.ts`.
     */
    public static function intermissionLabel(int $minutes): string
    {
        return 'Vaheaeg — '.$minutes.' min';
    }

    /**
     * How long the part of the show an interval *ends* runs, as its author
     * wrote it, or null when nobody has said. Only an interval carries this;
     * see {@see showParts()}, which is where it becomes the split the reader
     * sees. `actMinutes()` in the wizard's own `plan.ts` mirrors it.
     */
    public static function actMinutes(mixed $value): ?int
    {
        $minutes = is_numeric($value) ? (int) $value : 0;

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * How long each part of a show played in parts runs, so the technician can
     * see the evening's shape at a glance rather than working it out at the
     * desk.
     *
     * A part is a run of scenes with an interval beside it. Each interval says
     * how long the part it ends runs; the closing part is not asked, because it
     * is what is left of the evening — the show's own running time, less every
     * interval and every part already named. That subtraction only holds when
     * nothing is missing, so a part whose length cannot be worked out is
     * reported as null rather than guessed at.
     *
     * A show with nothing to split — no interval, or one standing before the
     * first scene or after the last — has no parts at all, and no reader shows
     * a split for it. Mirrored by `showParts()` in the wizard's own `plan.ts`.
     *
     * @param  array<int, mixed>  $scenes
     * @return array<int, array{num: int, start: int, minutes: int|null}>
     */
    public static function showParts(array $scenes, ?int $totalMinutes): array
    {
        $parts = [];
        $breaks = 0;
        $open = null;

        foreach (array_values($scenes) as $index => $scene) {
            $scene = is_array($scene) ? $scene : [];
            $interval = self::intermission($scene['intermission'] ?? null);

            if ($interval > 0) {
                $breaks += $interval;

                // An interval with no scenes behind it closes nothing: it
                // stands at the top of the running order, or straight after
                // another.
                if ($open !== null) {
                    $parts[] = ['start' => $open, 'minutes' => self::actMinutes($scene['actMinutes'] ?? null)];
                    $open = null;
                }

                continue;
            }

            $open ??= $index;
        }

        // Whatever the last interval left open closes the show.
        if ($open !== null) {
            $parts[] = ['start' => $open, 'minutes' => null];
        }

        if (count($parts) < 2) {
            return [];
        }

        $named = array_filter($parts, fn (array $part): bool => $part['minutes'] !== null);
        $last = count($parts) - 1;

        // The closing part is the only one worth working out, and only while it
        // is the only one left unsaid.
        if ($parts[$last]['minutes'] === null && $totalMinutes > 0 && count($named) === $last) {
            $rest = $totalMinutes - $breaks - array_sum(array_column($named, 'minutes'));

            $parts[$last]['minutes'] = $rest > 0 ? $rest : null;
        }

        return array_map(
            fn (array $part, int $index): array => ['num' => $index + 1] + $part,
            $parts,
            array_keys($parts),
        );
    }

    /**
     * One part of the show as every reader is shown it, its length included.
     * Mirrored by `actLabel()` in the wizard's own `plan.ts`.
     */
    public static function actLabel(int $num, ?int $minutes): string
    {
        return $minutes === null
            ? $num.'. vaatus'
            : $num.'. vaatus — '.$minutes.' min';
    }

    /**
     * The plan's sound block, filled out to the shape the wizard expects.
     *
     * @return array<string, string>
     */
    private function sound(): array
    {
        $sound = $this->resource->sound;

        return [
            'micsMode' => self::text($sound['micsMode'] ?? null, 'no'),
            'micsDetail' => self::text($sound['micsDetail'] ?? null),
            'musicianMode' => self::text($sound['musicianMode'] ?? null, 'no'),
            'musicianDetail' => self::text($sound['musicianDetail'] ?? null),
        ];
    }

    /**
     * The plan's equipment block, filled out to the shape the wizard expects.
     *
     * @return array<string, mixed>
     */
    private function equipment(): array
    {
        $equipment = $this->resource->equipment;

        $items = array_values((array) ($equipment['items'] ?? []));

        return [
            'items' => array_map(fn (array $item, int $index): array => [
                // The id is the row's list key in the wizard, so it must be set.
                'id' => self::text($item['id'] ?? null, 'seade-'.($index + 1)),
                'name' => self::text($item['name'] ?? null),
                'use' => self::text($item['use'] ?? null),
            ], $items, array_keys($items)),
            'smoke' => self::text($equipment['smoke'] ?? null, 'yes'),
            'suggestions' => self::text($equipment['suggestions'] ?? null, 'yes'),
            'suggestNote' => self::text($equipment['suggestNote'] ?? null),
        ];
    }
}
