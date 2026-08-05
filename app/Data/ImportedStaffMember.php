<?php

namespace App\Data;

use App\Enums\PerformanceStaffRole;
use App\Models\User;
use App\Services\StaffMemberLookup;

/**
 * One person doing one job at one performance, as a Planka card names them —
 * on stage or behind it. Still just a name at this point; turning it into a
 * {@see User} is {@see StaffMemberLookup}'s job, not
 * the extractor's.
 */
readonly class ImportedStaffMember
{
    public function __construct(
        public string $name,
        public PerformanceStaffRole $role,
    ) {
        //
    }
}
