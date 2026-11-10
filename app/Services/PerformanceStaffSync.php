<?php

namespace App\Services;

use App\Data\ImportedStaffMember;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes the imported part of a performance's staff exactly as the card last
 * read describes it. There is no editing here, only replacing: a name the card
 * dropped is gone from the table the next time it is read.
 *
 * Only the import's own rows are replaced. Whoever was put on the performance
 * by hand stays, whatever the card says — see {@see PerformanceStaff}.
 */
class PerformanceStaffSync
{
    public function __construct(protected StaffMemberLookup $lookup)
    {
        //
    }

    /**
     * Replace the imported staff of this performance with what the card says
     * now.
     *
     * A job the card gives somebody who already holds it by hand is not
     * written a second time: the hand-entered row already says it, and is the
     * one that survives the card changing its mind.
     *
     * @param  list<ImportedStaffMember>  $staff
     */
    public function sync(Performance $performance, array $staff): void
    {
        $manual = PerformanceStaff::query()
            ->where('performance_id', $performance->id)
            ->where('manually_added', true)
            ->get()
            ->mapWithKeys(fn (PerformanceStaff $row): array => [$this->key($row->user_id, $row->role->value) => true])
            ->all();

        $rows = [];
        $now = now();

        foreach ($staff as $member) {
            $user = $this->lookup->find($member->name);

            if ($user === null) {
                Log::warning('Could not match a Planka staff name to an account', [
                    'performance_id' => $performance->id,
                    'name' => $member->name,
                    'role' => $member->role->value,
                ]);

                continue;
            }

            $key = $this->key($user->id, $member->role->value);

            if (isset($manual[$key]) || isset($rows[$key])) {
                continue;
            }

            $rows[$key] = [
                'performance_id' => $performance->id,
                'user_id' => $user->id,
                'role' => $member->role->value,
                'manually_added' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($performance, $rows): void {
            PerformanceStaff::query()
                ->where('performance_id', $performance->id)
                ->where('manually_added', false)
                ->delete();

            if ($rows !== []) {
                PerformanceStaff::query()->insert(array_values($rows));
            }
        });
    }

    /**
     * One person in one job, as the unique key on the table sees it.
     */
    protected function key(int $userId, string $role): string
    {
        return $userId.':'.$role;
    }
}
