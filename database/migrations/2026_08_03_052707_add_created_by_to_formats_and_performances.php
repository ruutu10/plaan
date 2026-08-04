<?php

use App\Enums\CreatedBy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who put the record on the books: a person at the screen, or the weekly Planka
 * import. Nothing said so until now — a format that turned up on its own and one
 * somebody entered read exactly alike, which is the first question asked when a
 * date looks wrong.
 *
 * A record entered by hand is the ordinary case, so the column defaults to
 * "manual" and everything already on the books reads that way. The performances
 * carrying a card id are the exception: those demonstrably came off the board,
 * so they are set straight here rather than left claiming to have been typed in.
 * Formats keep no card id of their own, so they cannot be told apart in
 * retrospect and stay at the default.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->enum('created_by', CreatedBy::values())
                ->default(CreatedBy::Manual->value)
                ->after('description');
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->enum('created_by', CreatedBy::values())
                ->default(CreatedBy::Manual->value)
                ->after('is_draft');
        });

        DB::table('performances')
            ->whereNotNull('planka_card_id')
            ->update(['created_by' => CreatedBy::PlankaImport->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
