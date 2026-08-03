<?php

use App\Models\Performance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A plan is about a night, and the night is where everything that names the
 * plan comes from: the show, the group, the date, the running time. A plan
 * attached to no performance carried none of it — the crew's overview showed
 * blanks and the submission mail went out under a bare key — so the column
 * that was optional becomes the plan's spine.
 *
 * The plans already handed in with nothing to point at are moved onto the
 * stand-in performance ({@see Performance::placeholder()}), which is where
 * every such plan is filed from now on. Nothing is lost: the crew move them
 * onto the real performance once it has been registered.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('technical_plans')->whereNull('performance_id')->exists()) {
            DB::table('technical_plans')
                ->whereNull('performance_id')
                ->update(['performance_id' => Performance::placeholder()->id]);
        }

        // The column cannot be made NOT NULL while a foreign key stands ready
        // to write a null into it, so the constraint is replaced along with it.
        // A plan cannot outlive the performance it describes, which is what the
        // reminders of that performance already say by cascading.
        Schema::table('technical_plans', function (Blueprint $table) {
            $table->dropForeign(['performance_id']);
        });

        Schema::table('technical_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('performance_id')->nullable(false)->change();
        });

        Schema::table('technical_plans', function (Blueprint $table) {
            $table->foreign('performance_id')->references('id')->on('performances')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * The plans moved onto the stand-in performance stay there: which of them
     * arrived without a night of their own is not recorded, and guessing would
     * strip the show off plans that were filed under it deliberately.
     */
    public function down(): void
    {
        Schema::table('technical_plans', function (Blueprint $table) {
            $table->dropForeign(['performance_id']);
        });

        Schema::table('technical_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('performance_id')->nullable()->change();
        });

        Schema::table('technical_plans', function (Blueprint $table) {
            $table->foreign('performance_id')->references('id')->on('performances')->nullOnDelete();
        });
    }
};
