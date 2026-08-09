<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the house expects a technical plan for this format at all.
 *
 * Almost every format wants one, so the column defaults to true and everything
 * already on the books keeps being chased exactly as before. The exception is
 * the night that runs itself — an open stage, a jam, a warm-up the crew know by
 * heart — where the reminders were only ever noise, and a performer learning to
 * ignore one is a performer learning to ignore all of them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->boolean('technical_plan_mandatory')->default(true)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->dropColumn('technical_plan_mandatory');
        });
    }
};
