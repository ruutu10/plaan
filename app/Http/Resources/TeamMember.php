<?php

namespace App\Http\Resources;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person as the team-management screen lists them: who they are and what
 * they are in the team. The role comes off the membership row, so the resource
 * is only ever made from a user read through {@see Team::members()}.
 *
 * @property-read User $resource
 */
class TeamMember extends JsonResource
{
    /**
     * Transform the member into a listable row.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     role: string,
     *     roleLabel: string,
     *     isOwner: bool,
     * }
     */
    public function toArray(Request $request): array
    {
        $member = $this->resource;

        /** @var Membership $membership */
        $membership = $member->getRelation('pivot');

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $membership->role->value,
            'roleLabel' => $membership->role->label(),
            // The owner is the one member nobody may demote or take out.
            'isOwner' => $membership->role === TeamRole::Owner,
        ];
    }
}
