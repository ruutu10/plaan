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
                'contactEmail' => $plan->user->email ?? '',
            ],
            'sound' => $plan->sound,
            'scenes' => $plan->scenes,
            'equipment' => $plan->equipment,
            'extra' => [
                'notes' => $plan->extra['notes'] ?? '',
                'files' => $plan->attachmentsPayload(),
            ],
        ];
    }
}
