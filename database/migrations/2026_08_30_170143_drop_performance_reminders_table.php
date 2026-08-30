<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the hourly reminder run had already dealt with. Nothing runs on a
 * schedule any more: a reminder is sent by hand, to the members the crew picks
 * on the performance's own page, so there is no moment to claim and nothing to
 * keep from happening twice.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('performance_reminders');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('performance_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('performance_id')->constrained()->cascadeOnDelete();

            $table->string('schedule');

            // Null when the moment was written off rather than acted on.
            $table->timestamp('sent_at')->nullable();
            $table->unsignedSmallInteger('recipients')->default(0);

            $table->timestamps();

            $table->unique(['performance_id', 'schedule']);
        });
    }
};
