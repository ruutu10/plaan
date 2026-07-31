<?php

use App\Enums\ReminderSchedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the reminder run has already dealt with. A row records that a
 * performance has been looked at for one of the {@see ReminderSchedule}
 * moments and needs looking at no more — whether that ended in a mail going out
 * (`sent_at` set, `recipients` counted) or in the moment being written off
 * because it had already passed when the performance was registered.
 *
 * The unique key is the point of the table: an hourly job must not mail the
 * same performers twice because two runs overlapped.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reminders');
    }
};
