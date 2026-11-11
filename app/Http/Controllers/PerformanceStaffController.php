<?php

namespace App\Http\Controllers;

use App\Http\Requests\Performances\StorePerformanceStaffRequest;
use App\Http\Resources\PerformanceStaffMember as PerformanceStaffMemberResource;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use App\Services\PerformanceStaffSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The staff of a performance as entered by hand, beside what the Planka import
 * reads off the card. Whoever may change the night may put somebody on it —
 * a guest performer the card never named, a stand-in technician — and take
 * them off again.
 *
 * Only hand-added rows are ever removed here. The import's own are the card's
 * to say, and would be back on the next read of it anyway — see
 * {@see PerformanceStaffSync}.
 */
class PerformanceStaffController extends Controller
{
    /**
     * Put somebody on this performance's staff in the given role.
     */
    public function store(StorePerformanceStaffRequest $request, Format $format, Performance $performance): JsonResponse
    {
        /** @var PerformanceStaff $staffing */
        $staffing = $performance->staffings()->create([
            'user_id' => $request->integer('user_id'),
            'role' => $request->role(),
            'manually_added' => true,
        ]);

        Log::info('Staff member added to a performance by hand', [
            'performance_id' => $performance->id,
            'format_id' => $format->id,
            'staff_user_id' => $staffing->user_id,
            'role' => $staffing->role->value,
            'user_id' => $request->user()->id,
        ]);

        $member = $performance->staff()->wherePivot('id', $staffing->id)->sole();

        // Being on the staff opens the night's page and its plans, so who put
        // whom there is worth keeping past the app log's retention.
        activity()
            ->performedOn($performance)
            ->causedBy($request->user())
            ->event('staff_added')
            ->withProperties(['user_id' => $member->id, 'role' => $staffing->role->value])
            ->log(sprintf(
                '%s added to the staff as %s by %s',
                $member->name,
                $staffing->role->label(),
                $request->user()->name,
            ));

        return PerformanceStaffMemberResource::make($member)
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Take a hand-added job off this performance's staff.
     */
    public function destroy(Request $request, Format $format, Performance $performance, PerformanceStaff $staffing): Response
    {
        Gate::authorize('update', $performance);

        // The import's rows are the card's; removing one here would only last
        // until the next run, so the screen never offers it and the API
        // refuses it.
        abort_unless($staffing->manually_added, SymfonyResponse::HTTP_FORBIDDEN);

        $staffing->delete();

        Log::info('Staff member removed from a performance by hand', [
            'performance_id' => $performance->id,
            'format_id' => $format->id,
            'staff_user_id' => $staffing->user_id,
            'role' => $staffing->role->value,
            'user_id' => $request->user()->id,
        ]);

        activity()
            ->performedOn($performance)
            ->causedBy($request->user())
            ->event('staff_removed')
            ->withProperties(['user_id' => $staffing->user_id, 'role' => $staffing->role->value])
            ->log(sprintf(
                '%s taken off the staff as %s by %s',
                $staffing->user->name,
                $staffing->role->label(),
                $request->user()->name,
            ));

        return response()->noContent();
    }
}
