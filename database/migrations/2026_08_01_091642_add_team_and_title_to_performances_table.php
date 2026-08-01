<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who plays a performance, and under what name.
 *
 * A show used to have one owning group and its performances belonged to that
 * group by implication. That holds for an evening one troupe fills, and breaks
 * the moment several share a night: an Õppelava is one show played once, with
 * three or four different groups taking the stage one after another. So the
 * performing group moves onto the performance, and the act's own name — as the
 * board writes it — comes with it, since many of those acts are guests the
 * house has no group for.
 *
 * Both are nullable and both stay empty for every performance already on the
 * books: an empty group means the show's own, and an empty title means the
 * show's name already says who is playing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('show_id')->constrained()->nullOnDelete();
            $table->string('title')->nullable()->after('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn('title');
        });
    }
};
