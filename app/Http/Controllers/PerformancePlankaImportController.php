<?php

namespace App\Http\Controllers;

use App\Events\PlankaReimportRequested;
use App\Listeners\ImportPlankaCardsForPerformance;
use App\Models\Format;
use App\Models\Performance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Reading one performance off the Planka board again, on request.
 *
 * The nightly run keeps the books level with the board on its own; this is for
 * the hour after somebody has just moved a card and wants the change here now.
 * Nothing is imported in the request itself — see
 * {@see ImportPlankaCardsForPerformance}, which is queued.
 */
class PerformancePlankaImportController extends Controller
{
    /**
     * Set a Planka import going for the cards this performance is named on.
     */
    public function store(Request $request, Format $format, Performance $performance): JsonResponse
    {
        Gate::authorize('reimportFromPlanka', $performance);

        $filterTitle = $performance->setRelation('format', $format)->plankaImportFilter();

        PlankaReimportRequested::dispatch($performance, $filterTitle, $request->user());

        // Accepted, not done: the answer says what was asked for, and the run
        // itself happens on a queue where the browser cannot see it.
        return response()->json(['filterTitle' => $filterTitle], Response::HTTP_ACCEPTED);
    }
}
