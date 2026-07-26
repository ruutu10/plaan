<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split the show's concept off the performance: what a show is called, is about
 * and belongs to now lives in `shows`, while a performance keeps only what
 * differs between stagings — its date and duration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['show_name', 'description']);

            $table->foreignId('show_id')->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->renameColumn('show_date', 'date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('show_id');

            $table->foreignId('team_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('show_name')->after('team_id');
            $table->text('description')->nullable();
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->renameColumn('date', 'show_date');
        });

        Schema::dropIfExists('shows');
    }
};
