<?php

use App\Models\Format;
use App\Models\Performance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A format may be explained by more than one card.
 *
 * The link table was built on the assumption that a record has one reading
 * behind it, which holds for a performance and does not hold for a format: an
 * Õppelava is created by the first card that announces one and then gathers a
 * night from every card after it, each read on its own. Holding it to one
 * reading meant the format could only ever show its first, from an evening months
 * ago, while the cards that filled the rest of the season explained nothing.
 *
 * So the key moves to the pair plus the reading: a record may be explained many
 * times over, but the same reading is never attached to it twice.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('claude_reasoning_log_subjects', function (Blueprint $table) {
            $table->dropUnique(['subject_type', 'subject_id']);
            $table->unique(['subject_type', 'subject_id', 'claude_reasoning_log_id'], 'claude_log_subject_reading_unique');
        });

        $this->explainFormatsByTheirPerformances();
    }

    /**
     * Give the formats already imported the readings they could not hold before.
     *
     * A reading that explains a performance explains the format it was put on, so
     * the links are there to be derived — and without this, a format would only
     * catch up the next time a card happened to add a night to it.
     */
    private function explainFormatsByTheirPerformances(): void
    {
        $rows = DB::table('claude_reasoning_log_subjects as links')
            ->join('performances', 'performances.id', '=', 'links.subject_id')
            ->where('links.subject_type', Performance::class)
            ->select('links.claude_reasoning_log_id', 'performances.format_id')
            ->distinct()
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('claude_reasoning_log_subjects')->insertOrIgnore([
                'claude_reasoning_log_id' => $row->claude_reasoning_log_id,
                'subject_type' => Format::class,
                'subject_id' => $row->format_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * A record explained by several cards has no one reading to keep, so the
     * later ones are dropped rather than picked between.
     */
    public function down(): void
    {
        // Every link that has an older one for the same record — said as a join
        // rather than as a list of ids to keep, so an empty table cannot be
        // read as "keep nothing" and emptied further.
        DB::table('claude_reasoning_log_subjects as later')
            ->join('claude_reasoning_log_subjects as earlier', function (JoinClause $join): void {
                $join->on('earlier.subject_type', '=', 'later.subject_type')
                    ->on('earlier.subject_id', '=', 'later.subject_id')
                    ->whereColumn('earlier.id', '<', 'later.id');
            })
            ->delete();

        Schema::table('claude_reasoning_log_subjects', function (Blueprint $table) {
            $table->dropUnique('claude_log_subject_reading_unique');
            $table->unique(['subject_type', 'subject_id']);
        });
    }
};
