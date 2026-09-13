<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The conversation about a plan, kept beside the plan rather than in the
 * performers' and the crew's separate inboxes. A comment is written on the
 * plan's own overview page, and the other side is mailed about it — see
 * App\Listeners\NotifyPlanCommented.
 *
 * Every comment names its writer: a plan is only commented on by somebody
 * signed in, and the thread reads as a conversation between named people. A
 * comment whose writer is later removed goes with them — there is nobody left
 * for the other side to answer.
 *
 * Whether a comment came from the technical team is written down rather than
 * worked out on the way past: it decides both who is mailed and how the comment
 * is badged, and it should go on saying what was true the day it was written
 * even after its writer has joined or left the crew.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('technical_plan_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('from_technical_team')->default(false);
            $table->timestamps();

            // The thread is always read oldest-first for one plan at a time.
            $table->index(['technical_plan_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_plan_comments');
    }
};
