<?php

use App\Models\Performance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A format is not a card: it is played on several nights, each its own
 * {@see Performance}, and each already carries the card it was
 * announced on. A card id on the format itself named no particular night, so
 * whichever card happened to create or last touch the format — not necessarily
 * the one anybody actually wants when a date looks wrong.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->dropColumn('planka_card_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->string('planka_card_id')->nullable()->after('description');
        });
    }
};
