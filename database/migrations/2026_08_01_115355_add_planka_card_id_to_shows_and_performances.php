<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The card on the board a record came from.
 *
 * The season is planned in Planka, and the import reads it from there, but until
 * now nothing on a show or a performance said which card it was read off. That
 * is the first thing anybody wants when a date looks wrong: not the reasoning,
 * the card itself — where the crew, the bar rota and whatever was decided after
 * the import ran are still being written.
 *
 * Nullable and hand-editable: a show entered by hand has no card, and one the
 * board later moved to another card is corrected on the screen rather than by
 * re-importing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->string('planka_card_id')->nullable()->after('description');
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->string('planka_card_id')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn('planka_card_id');
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->dropColumn('planka_card_id');
        });
    }
};
