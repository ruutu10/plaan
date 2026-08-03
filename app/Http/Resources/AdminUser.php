<?php

namespace App\Http\Resources;

use App\Models\User as UserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One account as the management screens list and edit it: who it belongs to,
 * where it came from, and which roles it holds. The roles ride along only where
 * they were loaded, so a listing that forgets to eager-load them says nothing
 * rather than reading them a user at a time.
 *
 * Nothing secret travels here — no password, no two-factor secret — because a
 * technician keeping accounts straight has no business with either.
 *
 * @property-read UserModel $resource
 */
class AdminUser extends JsonResource
{
    /**
     * Transform the account into a listable and editable row.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     emailVerified: bool,
     *     signupSource: string,
     *     signupSourceLabel: string,
     *     createdAt: string|null,
     *     teamCount: int|null,
     *     roles: mixed,
     * }
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            // An unproven address is worth seeing here: it is the one thing
            // that keeps the house's own domains from being taken on as staff.
            'emailVerified' => $user->hasVerifiedEmail(),
            // Every account came through one of the doors; the column has a
            // default and is never empty.
            'signupSource' => $user->signup_source->value,
            'signupSourceLabel' => $user->signup_source->label(),
            'createdAt' => $user->created_at?->toIso8601String(),
            'teamCount' => $user->teams_count,
            'roles' => AdminRole::collection($this->whenLoaded('roles')),
        ];
    }
}
