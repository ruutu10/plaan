<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the technical crew's overview of every plan in the house: what is
 * being staged, by whom, when, who handed the plan in and how far it has got.
 *
 * @property-read TechnicalPlanModel $resource
 */
class AdminTechnicalPlan extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * Transform the plan into a listable row.
     *
     * @return array{
     *     token: string,
     *     formatName: string|null,
     *     teamName: string|null,
     *     performanceDate: string|null,
     *     submittedBy: string|null,
     *     submittedByEmail: string|null,
     *     status: string,
     *     statusLabel: string,
     *     submittedAt: string|null,
     *     url: string,
     * }
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;
        $format = $plan->performance?->format;

        return [
            'token' => $plan->token,
            'formatName' => $format?->name,
            'teamName' => $plan->performance?->performerName(),
            'performanceDate' => $plan->performance?->startDate(),
            'submittedBy' => $plan->user?->name,
            'submittedByEmail' => $plan->user?->email,
            'status' => $plan->status->value,
            'statusLabel' => $plan->status->label(),
            'submittedAt' => $plan->submitted_at?->toDateString(),
            'url' => route('technical-plan.public', $plan),
        ];
    }
}
