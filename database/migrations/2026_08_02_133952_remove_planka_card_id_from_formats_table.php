<?php

use App\Models\Performance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A show is not a card: it is played on several nights, each its own
 * {@see Performance}, and each already carries the card it was
 * announced on. A card id on the show itself named no particular night, so
 * whichever card happened to create or last touch the show — not necessarily
 * the one anybody actually wants when a date looks wrong.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn('planka_card_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->string('planka_card_id')->nullable()->after('description');
        });
    }
};
