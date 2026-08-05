<?php

namespace App\Services;

use App\Data\ImportedStaffMember;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes a performance's staff exactly as the card last read describes it.
 * There is no editing here, only replacing: a name the card dropped is gone
 * from the row the next time it is read, which is what "imported, not
 * entered" is supposed to mean for this table — see {@see PerformanceStaff}.
 */
class PerformanceStaffSync
{
    public function __construct(protected StaffMemberLookup $lookup)
    {
        //
    }

    /**
     * Replace everything this performance's staff table says with what the
     * card says now.
     *
     * @param  list<ImportedStaffMember>  $staff
     */
    public function sync(Performance $performance, array $staff): void
    {
        $rows = [];
        $now = now();

        foreach ($staff as $member) {
            $user = $this->lookup->find($member->name);

            if ($user === null) {
                Log::warning('Could not match a Planka staff name to a house account', [
                    'performance_id' => $performance->id,
                    'name' => $member->name,
                    'role' => $member->role->value,
                ]);

                continue;
            }

            $rows[] = [
                'performance_id' => $performance->id,
                'user_id' => $user->id,
                'role' => $member->role->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($performance, $rows): void {
            PerformanceStaff::query()->where('performance_id', $performance->id)->delete();

            if ($rows !== []) {
                PerformanceStaff::query()->insert($rows);
            }
        });
    }
}
