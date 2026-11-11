<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a recording's own subtitles say happened on stage, written up once.
 *
 * On the recording rather than the performance because the subtitles it is
 * read from belong to one video: a corrected link is another video, and starts
 * its own wait — see App\Models\PerformanceRecording::booted(). The design is
 * in docs/adr/0001-performance-plot-summary-from-subtitles.md.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performance_recordings', function (Blueprint $table) {
            // The summary itself — the model's, or the crew's correction of it.
            $table->text('plot_summary')->nullable()->after('announced_at');
            // When the model wrote one. The "once" in "once per recording": a
            // summary cleared by hand afterwards is not written again unasked.
            $table->timestamp('plot_summary_generated_at')->nullable()->after('plot_summary');
            // When the house stopped waiting — no subtitles within the window,
            // or subtitles too garbled to read a plot out of.
            $table->timestamp('plot_summary_abandoned_at')->nullable()->after('plot_summary_generated_at');
            // When the current link was written, which the wait is counted from.
            $table->timestamp('linked_at')->nullable()->after('url');
        });

        // Counted from now rather than from when each link was made: recordings
        // linked long before this existed are the ones most likely to have
        // their subtitles already, and deserve the same window as a new one.
        DB::table('performance_recordings')->update(['linked_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_recordings', function (Blueprint $table) {
            $table->dropColumn(['plot_summary', 'plot_summary_generated_at', 'plot_summary_abandoned_at', 'linked_at']);
        });
    }
};
