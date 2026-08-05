<?php

namespace App\Http\Controllers;

use App\Models\Format;
use App\Models\Performance;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Inertia shell of a single performance's details screen. Carries no
 * performance data of its own: the page fetches what it needs from
 * {@see PerformanceController}'s JSON API, the same way {@see
 * FormatPageController} serves the format it belongs to.
 */
class PerformancePageController extends Controller
{
    /**
     * Render a single performance's details.
     */
    public function show(Format $format, Performance $performance): Response
    {
        Gate::authorize('view', $performance);

        return Inertia::render('formats/performances/Show', [
            'formatId' => $format->id,
            'performanceId' => $performance->id,
        ]);
    }
}
