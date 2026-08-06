<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notes are the model's own summary of a reading; this is the reading
 * itself. Kept alongside them so a note that turns out to be wrong — or too
 * short to trust — can be checked against exactly what the model answered,
 * without having to reproduce the request to find out.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('claude_reasoning_logs', function (Blueprint $table) {
            $table->json('raw_response')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claude_reasoning_logs', function (Blueprint $table) {
            $table->dropColumn('raw_response');
        });
    }
};
