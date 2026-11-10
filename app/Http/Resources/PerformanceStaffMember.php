<?php

namespace App\Http\Resources;

use App\Models\Performance;
use App\Models\PerformanceStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person staffing one performance, as the details screen lists them: who
 * they are, what they do that night, and whether the import or a person put
 * them there. The role comes off the pivot row, so the resource is only ever
 * made from a user read through {@see Performance::staff()}.
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
     *     staffingId: int,
     *     name: string,
     *     email: string,
     *     role: string,
     *     roleLabel: string,
     *     manuallyAdded: bool,
     * }
     */
    public function toArray(Request $request): array
    {
        $member = $this->resource;

        /** @var PerformanceStaff $staffing */
        $staffing = $member->getRelation('pivot');

        return [
            'id' => $member->id,
            // The row itself, which is what a hand-added job is removed by: a
            // person can hold more than one job on a night.
            'staffingId' => (int) $staffing->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $staffing->role->value,
            'roleLabel' => $staffing->role->label(),
            // Hand-added rows are the only ones the screen offers to remove;
            // the import's own come back on the next read of the card anyway.
            'manuallyAdded' => (bool) $staffing->manually_added,
        ];
    }
}
