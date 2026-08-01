<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why the import read a card the way it did, kept where it can be found again.
 *
 * The weekly Planka import hands each card to Claude, which returns its own
 * account of the reading: where it took the date from, why an evening became one
 * night or three, why a group was matched or left off. That account used to live
 * only in the run's output and the log, which is nowhere at all by the time
 * somebody notices a show with the wrong name months later.
 *
 * One row per card read, and a second table saying which records that card
 * produced. The link is polymorphic — the first in this codebase — because a
 * card makes shows and performances alike, and the question being answered
 * ("why does this record look like this?") is the same for both. A record has at
 * most one log, which is what the unique key is for.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('claude_reasoning_logs', function (Blueprint $table) {
            $table->id();

            // The card as Planka knows it, so a log can be taken back to the
            // board it came from. Both nullable: neither is what the row is
            // about, and a reading may one day come from somewhere else.
            $table->string('card_id')->nullable();
            $table->string('card_name')->nullable();

            // The notes as the model wrote them, one line per decision.
            $table->json('notes');

            $table->timestamps();
        });

        Schema::create('claude_reasoning_log_subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('claude_reasoning_log_id')->constrained()->cascadeOnDelete();

            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->timestamps();

            // One log per record: a second reading of the same card must not
            // leave the screens with two explanations to choose between.
            $table->unique(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claude_reasoning_log_subjects');
        Schema::dropIfExists('claude_reasoning_logs');
    }
};
