<?php

namespace App\Http\Resources;

use App\Models\Performance;
use App\Models\PerformanceStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person staffing one performance, as the details screen lists them: who
 * they are and what they do that night. The role comes off the pivot row, so
 * the resource is only ever made from a user read through
 * {@see Performance::staff()}.
 *
 * @property-read User $resource
 */
class PerformanceStaffMember extends JsonResource
{
    /**
     * Transform the staff member into a listable row.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     role: string,
     *     roleLabel: string,
     * }
     */
    public function toArray(Request $request): array
    {
        $member = $this->resource;

        /** @var PerformanceStaff $staffing */
        $staffing = $member->getRelation('pivot');

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $staffing->role->value,
            'roleLabel' => $staffing->role->label(),
        ];
    }
}
