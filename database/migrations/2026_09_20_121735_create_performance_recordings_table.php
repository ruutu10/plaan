<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the video of a played night lives, and how that night's account of
 * itself was last pushed to it.
 *
 * A recording is its own record rather than a handful of columns on the
 * performance because almost none of it is about the performance: one column
 * is what a person pasted, and the rest is an integration's bookkeeping —
 * when it last wrote to the media server, what went wrong if it did not, and
 * whether the performers have been told the video exists.
 *
 * `performance_id` is unique: a night has one recording or none. The row is
 * soft-deleted rather than removed when the link is cleared, so `announced_at`
 * outlives the clearing and a corrected link never writes to the performers a
 * second time about the same evening.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('performance_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->unique()->constrained()->cascadeOnDelete();
            // What was pasted, kept whole: a Jellyfin address carries the
            // server it belongs to alongside the item, and that matters on a
            // house with more than one.
            $table->string('url', 2048);
            // The same address as the item it names — dash-less lowercase hex,
            // which is the form the API takes. Derived when the URL is written
            // rather than parsed again at call time, so a push made minutes
            // later writes to exactly the item the link was accepted as.
            $table->string('item_id', 32)->index();
            // The last push that got through, and what stopped the last one
            // that did not. Empty on a live row means it is queued or failed.
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();
            // When the performers were told there is a video. Written once and
            // never again — see the class docblock above.
            $table->timestamp('announced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_recordings');
    }
};
