<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Work out which existing plans a technician approved.
     *
     * A plan still marked received plainly was. An archived one has lost that,
     * so its audit trail decides: approved only if the last thing recorded was
     * a technician's approval, not a later withdrawal or fresh submission. No
     * trail means no approval, because this column opens access to video.
     */
    public function up(): void
    {
        DB::table('technical_plans')
            ->where('status', 'received')
            ->whereNull('approved_at')
            ->update(['approved_at' => DB::raw('updated_at')]);

        DB::table('technical_plans')
            ->where('status', 'archived')
            ->whereNull('approved_at')
            ->select('id')
            ->lazyById()
            ->each(function (object $plan): void {
                $entry = DB::table('activity_log')
                    ->where('subject_type', 'App\Models\TechnicalPlan')
                    ->where('subject_id', $plan->id)
                    ->whereIn('event', ['status_changed', 'submitted'])
                    ->orderByDesc('id')
                    ->first();

                if ($entry === null || $entry->event !== 'status_changed') {
                    return;
                }

                $change = json_decode((string) $entry->properties, true) ?: [];
                $from = $change['from'] ?? null;
                $to = $change['to'] ?? null;

                if ($to === 'received' || ($to === 'archived' && $from === 'received')) {
                    DB::table('technical_plans')->where('id', $plan->id)->update(['approved_at' => $entry->created_at]);
                }
            });
    }

    /**
     * Nothing to undo: dropping the column removes what this wrote.
     */
    public function down(): void
    {
        //
    }
};
