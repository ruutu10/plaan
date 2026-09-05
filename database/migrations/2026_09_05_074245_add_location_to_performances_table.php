<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a performance is played.
 *
 * The house does not play everything in its own room: a card names the venue in
 * a line of its own — "Asukoht: improkeskus" — and a plan written for a night at
 * a rented hall is written against a different room than one at home. So the
 * place travels with the performance rather than being assumed.
 *
 * Free text, and nullable: the board writes venues by hand and half the cards
 * name none at all, which reads as the house's usual room rather than as a gap
 * worth chasing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->string('location')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
