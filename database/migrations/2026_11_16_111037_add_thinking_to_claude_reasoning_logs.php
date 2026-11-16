<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notes say how the model read a card; this is what it thought through on
 * the way there, as the API summarises it. Empty for the many cards the model
 * reads without stopping to think, which at a low effort is most of them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('claude_reasoning_logs', function (Blueprint $table) {
            $table->longText('thinking')->nullable()->after('raw_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claude_reasoning_logs', function (Blueprint $table) {
            $table->dropColumn('thinking');
        });
    }
};
