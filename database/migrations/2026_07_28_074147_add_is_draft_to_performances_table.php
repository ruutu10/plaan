<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A performance the house has not vouched for yet. The Planka import reads
 * dates and durations off cards written for people rather than for us, so what
 * it registers waits as a draft until somebody has looked it over; only then is
 * it offered as a performance a technical plan can be written for. A performance
 * entered by hand is vouched for by the entering, so the column defaults to
 * false and every performance already on the books stays listed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
