<?php

namespace App\Services;

use App\Data\ImportedStaffMember;
use App\Enums\PerformanceStaffRole;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use Illuminate\Support\Collection;
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
        $names = [];
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

            $names[$user->id] = $user->name;

            $rows[$key] = [
                'performance_id' => $performance->id,
                'user_id' => $user->id,
                'role' => $member->role->value,
                'manually_added' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($performance, $rows, $names): void {
            $previous = PerformanceStaff::query()
                ->with('user')
                ->where('performance_id', $performance->id)
                ->where('manually_added', false)
                ->get()
                ->keyBy(fn (PerformanceStaff $row): string => $this->key($row->user_id, $row->role->value));

            PerformanceStaff::query()
                ->where('performance_id', $performance->id)
                ->where('manually_added', false)
                ->delete();

            if ($rows !== []) {
                PerformanceStaff::query()->insert(array_values($rows));
            }

            $this->logChanges(
                $performance,
                added: array_diff_key($rows, $previous->all()),
                removed: $previous->diffKeys($rows),
                names: $names,
            );
        });
    }

    /**
     * Put on the audit trail who the card put on the night and who it took off
     * — the rows here are rewritten in bulk, which no model event sees. A read
     * that changed nothing leaves no entry.
     *
     * Filed as the system's doing: the import runs on a queue, and whoever
     * asked for it has an entry of their own for that.
     *
     * @param  array<string, array{user_id: int, role: string}>  $added
     * @param  Collection<string, PerformanceStaff>  $removed
     * @param  array<int, string>  $names  the names of the accounts being added, by id
     */
    protected function logChanges(Performance $performance, array $added, Collection $removed, array $names): void
    {
        if ($added === [] && $removed->isEmpty()) {
            return;
        }

        $addedLines = array_map(
            fn (array $row): string => sprintf('%s (%s)', $names[$row['user_id']], PerformanceStaffRole::from($row['role'])->label()),
            array_values($added),
        );
        $removedLines = $removed
            ->map(fn (PerformanceStaff $row): string => sprintf('%s (%s)', $row->user->name, $row->role->label()))
            ->values()
            ->all();

        activity()
            ->performedOn($performance)
            ->causedByAnonymous()
            ->event('staff_synced')
            ->withProperties([
                'added' => array_map(
                    fn (array $row): array => ['user_id' => $row['user_id'], 'role' => $row['role']],
                    array_values($added),
                ),
                'removed' => $removed
                    ->map(fn (PerformanceStaff $row): array => ['user_id' => $row->user_id, 'role' => $row->role->value])
                    ->values()
                    ->all(),
            ])
            ->log(implode('; ', array_filter([
                'Staff read off Planka',
                $addedLines === [] ? null : 'added '.implode(', ', $addedLines),
                $removedLines === [] ? null : 'taken off '.implode(', ', $removedLines),
            ])));
    }

    /**
     * One person in one job, as the unique key on the table sees it.
     */
    protected function key(int $userId, string $role): string
    {
        return $userId.':'.$role;
    }
}
