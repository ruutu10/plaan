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
            'meta' => [
                'performanceId' => $plan->performance_id,
                'performer' => $plan->performance?->show->team->name ?? '',
                'showName' => $plan->performance?->show->name ?? '',
                'showDate' => $plan->performance?->date?->format('Y-m-d') ?? '',
                'duration' => $plan->performance?->duration,
                'description' => $plan->performance?->show->description ?? '',
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
     * A stored JSON value the wizard expects as a string. Every wizard field is
     * optional, so a plan can hold a `null` (or nothing at all) where the
     * frontend's `Plan` shape promises text — hand it the empty string instead.
     */
    public static function text(mixed $value, string $default = ''): string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
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
