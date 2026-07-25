<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
     * Whether the file handles should point at fresh staged copies of the
     * attachments rather than at the plan's own media.
     */
    private bool $duplicateAttachments = false;

    /**
     * Serialise the plan as the basis for a *new* plan: its attachments are
     * duplicated into staged uploads, so submitting the copy carries the files
     * over without affecting this plan.
     */
    public function withDuplicatedAttachments(): static
    {
        $this->duplicateAttachments = true;

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
                'performer' => $plan->performance?->team->name ?? '',
                'showName' => $plan->performance?->show_name ?? '', // @phpstan-ignore-line
                'showDate' => $plan->performance?->show_date?->format('Y-m-d') ?? '',
                'duration' => $plan->performance?->duration,
                'description' => $plan->performance?->description ?? '', // @phpstan-ignore-line
            ],
            'sound' => $plan->sound,
            'scenes' => $plan->scenes,
            'equipment' => $plan->equipment,
            'extra' => [
                'notes' => $plan->extra['notes'] ?? '',
                'files' => Attachment::collection($this->duplicateAttachments
                    ? $plan->duplicateAttachmentsToStaging()
                    : $plan->attachments())->resolve($request),
            ],
        ];
    }
}
