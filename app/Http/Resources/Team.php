<?php

namespace App\Http\Resources;

use App\Models\Team as TeamModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One team as the management screens show it: what it is called, how many
 * people belong to it and how much it has staged. The members themselves ride
 * along only where they were loaded — the listing does not need them.
 *
 * @property-read TeamModel $resource
 */
class Team extends JsonResource
{
    /**
     * Transform the team into a listable and editable row.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     memberCount: int|null,
     *     formatCount: int|null,
     *     members: mixed,
     * }
     */
    public function toArray(Request $request): array
    {
        $team = $this->resource;

        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'memberCount' => $team->members_count,
            'formatCount' => $team->formats_count,
            'members' => TeamMember::collection($this->whenLoaded('members')),
        ];
    }
}
