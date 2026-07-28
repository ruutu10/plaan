<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A past plan for the same show offered as the basis for a new one, labelled by
 * the date of the performance it was written for. A plan handed in by a team-mate
 * also names its author — the plan being taken over is somebody else's work.
 * Only serialised nested inside an {@see UpcomingPerformance}.
 *
 * @property-read TechnicalPlanModel $resource
 */
class PriorPlan extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * Transform the plan into a pickable option.
     *
     * @return array{token: string, label: string, author: string|null}
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;

        return [
            'token' => $plan->token,
            'label' => $plan->performance?->date?->format('d.m.Y') ?? '',
            'author' => $plan->user_id === $request->user()?->id ? null : $plan->user?->name,
        ];
    }
}
