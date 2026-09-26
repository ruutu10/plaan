<?php

use App\Enums\CreatedBy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the Planka import last read a performance off its card.
 *
 * The import comes back to a performance every week — and whenever somebody
 * asks for the card to be read again — to rewrite its crew and venue. When it
 * was put on the books says nothing about how fresh those are, and neither does
 * `updated_at`, which a hand edit moves too and an unchanged reading does not.
 *
 * Performances the import already registered start from the reading that
 * registered them, the one reading of them known for certain.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->timestamp('planka_imported_at')->nullable()->after('planka_card_id');
        });

        DB::table('performances')
            ->where('created_by', CreatedBy::PlankaImport->value)
            ->update(['planka_imported_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropColumn('planka_imported_at');
        });
    }
};
