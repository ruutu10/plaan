<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A past plan offered as the basis for a new one, labelled by the date of the
 * staging it was written for. Only serialised nested inside an
 * {@see UpcomingPerformance}.
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
     * @return array{token: string, label: string}
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;

        return [
            'token' => $plan->token,
            'label' => $plan->performance?->show_date?->format('d.m.Y') ?? '',
        ];
    }
}
