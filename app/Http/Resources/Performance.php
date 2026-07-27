<?php

namespace App\Http\Resources;

use App\Models\Performance as PerformanceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One dated staging of a show, as the management screens list and edit it.
 *
 * @property-read PerformanceModel $resource
 */
class Performance extends JsonResource
{
    /**
     * Transform the staging into a listable and editable row.
     *
     * @return array{
     *     id: int,
     *     date: string,
     *     duration: int|null,
     *     technicalPlanCount: int|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $performance = $this->resource;

        return [
            'id' => $performance->id,
            'date' => $performance->date->toDateString(),
            'duration' => $performance->duration,
            // Deleting a staging leaves the plans written for it behind without
            // one, so the screen warns before that happens.
            'technicalPlanCount' => $performance->technical_plans_count,
        ];
    }
}
