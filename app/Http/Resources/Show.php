<?php

namespace App\Http\Resources;

use App\Models\Show as ShowModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One show as the management screens show it: what it is called, what it is
 * about, whose it is, and how many stagings hang off it.
 *
 * @property-read ShowModel $resource
 */
class Show extends JsonResource
{
    /**
     * Transform the show into a listable and editable row.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     description: string|null,
     *     teamId: int|null,
     *     teamName: string|null,
     *     performanceCount: int|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $show = $this->resource;

        return [
            'id' => $show->id,
            'name' => $show->name,
            'description' => $show->description,
            'teamId' => $show->team_id,
            'teamName' => $show->team?->name,
            // Only the listing counts the stagings; the edit page does not.
            'performanceCount' => $show->performances_count,
        ];
    }
}
