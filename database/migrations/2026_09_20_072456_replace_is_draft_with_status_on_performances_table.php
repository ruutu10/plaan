<?php

use App\Enums\PerformanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the draft flag into the three standings a performance can actually
 * have. The flag only ever said "imported and not looked at yet" or "not that";
 * the second half of it was two different things all along — a night still to
 * be played and one already behind us — and only the first belongs in the
 * listings the house is offered.
 *
 * Nothing is lost on the way across: every draft stays a draft, and every
 * performance the house had vouched for becomes upcoming, or archived when its
 * curtain-up has already passed. The same reading happens weekly from then on —
 * see App\Console\Commands\ArchivePerformances.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->enum('status', PerformanceStatus::values())
                ->default(PerformanceStatus::Upcoming->value)
                ->after('duration')
                ->index();
        });

        // Soft-deleted rows are read too: one restored later should come back
        // standing where it stood, not as whatever the column defaults to.
        DB::table('performances')
            ->where('is_draft', true)
            ->update(['status' => PerformanceStatus::Draft->value]);

        DB::table('performances')
            ->where('is_draft', false)
            ->where('date', '<', now())
            ->update(['status' => PerformanceStatus::Archived->value]);

        Schema::table('performances', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }

    /**
     * Reverse the migrations.
     *
     * The three standings fold back into the two the flag could hold: an
     * archived performance was one the house had vouched for, so it goes back
     * as such. Which of them had been played is not lost either — it is the
     * date, which is what the status was read off in the first place.
     */
    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('duration');
        });

        DB::table('performances')
            ->where('status', PerformanceStatus::Draft->value)
            ->update(['is_draft' => true]);

        // The index goes first: SQLite refuses to drop a column an index still
        // names, and it would be left behind pointing at nothing anyway.
        Schema::table('performances', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
