<?php

namespace App\Http\Controllers;

use App\Actions\RemindAboutTechnicalPlan;
use App\Http\Requests\Performances\SendPerformanceReminderRequest;
use App\Models\Format;
use App\Models\Performance;
use Illuminate\Http\JsonResponse;

/**
 * The reminder a night's performers are chased with when the technical plan is
 * still missing.
 *
 * Sent by hand and never on a schedule: whoever may change the performance
 * opens it, picks the members of the group playing it, and presses send. Each
 * of them gets a letter of their own — see {@see RemindAboutTechnicalPlan}.
 */
class PerformanceReminderController extends Controller
{
    /**
     * Chase the chosen performers about this performance's missing plan.
     */
    public function store(
        SendPerformanceReminderRequest $request,
        RemindAboutTechnicalPlan $remind,
        Format $format,
        Performance $performance,
    ): JsonResponse {
        $sent = $remind->handle(
            $performance->setRelation('format', $format),
            $request->performers(),
            $request->user(),
        );

        return response()->json(['sent' => $sent]);
    }
}
