<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A show is the concept — what it is called, what it is about and whose it is.
 * A performance is one dated playing of it, keeping only what differs between
 * them. Both are put aside rather than destroyed when deleted: the plans
 * written for them, and the record that they were ever staged, outlive their
 * usefulness in the lists.
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
            $table->softDeletes();
        });

        Schema::create('performances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('show_id')->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->unsignedSmallInteger('duration')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performances');
        Schema::dropIfExists('shows');
    }
};
