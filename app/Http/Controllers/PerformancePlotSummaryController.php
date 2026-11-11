<?php

namespace App\Http\Controllers;

use App\Events\PerformancePlotSummaryRequested;
use App\Listeners\SummarisePerformancePlot;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A fresh plot summary for a night's recording, on request.
 *
 * The daily run writes one summary per recording and leaves it be; this is for
 * a summary that read the night wrong, or a recording given up on that has
 * since been transcribed. Nothing is read in the request itself — see
 * {@see SummarisePerformancePlot}, which is queued.
 */
class PerformancePlotSummaryController extends Controller
{
    /**
     * Start the wait for this recording's subtitles over, and ask after them
     * now. The summary already shown stays until a new one replaces it.
     */
    public function store(Request $request, Format $format, Performance $performance): JsonResponse
    {
        Gate::authorize('regeneratePlotSummary', $performance);

        /** @var PerformanceRecording $recording */
        $recording = $performance->recording;

        $recording->restartPlotSummaryWait();

        activity()
            ->performedOn($recording)
            ->causedBy($request->user())
            ->event('plot_summary_regeneration_requested')
            ->log(sprintf('Plot summary regeneration requested by %s', $request->user()->name));

        PerformancePlotSummaryRequested::dispatch($recording, $request->user());

        // Accepted, not done: the reading happens on a queue.
        return response()->json(null, Response::HTTP_ACCEPTED);
    }
}
