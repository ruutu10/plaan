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
 * {@see ImportPlankaCardsForPerformance}, which is queued, and which asks the
 * model about the card afresh rather than taking last week's reading of it.
 */
class PerformancePlankaImportController extends Controller
{
    /**
     * Set a Planka import going for the card this performance was announced on.
     *
     * A performance that knows no card has nothing to be read off: guessing at
     * its card by title could catch a neighbouring one and register nights
     * nobody asked about, so the request is refused until a card is named.
     */
    public function store(Request $request, Format $format, Performance $performance): JsonResponse
    {
        Gate::authorize('reimportFromPlanka', $performance);

        if (blank($performance->planka_card_id)) {
            return response()->json(
                ['message' => 'Etendusel pole Planka kaardi ID-d, mille järgi importida.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        PlankaReimportRequested::dispatch($performance, $performance->planka_card_id, $request->user());

        // Accepted, not done: the answer says what was asked for, and the run
        // itself happens on a queue where the browser cannot see it.
        return response()->json(['cardId' => $performance->planka_card_id], Response::HTTP_ACCEPTED);
    }
}
