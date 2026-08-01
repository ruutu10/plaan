<?php

namespace App\Http\Controllers;

use App\Http\Requests\Performances\SavePerformanceRequest;
use App\Http\Resources\Performance as PerformanceResource;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Policies\PerformancePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The JSON API behind a show's dated performances. Every route is nested under the
 * show and the bindings are scoped to it, so a performance is only ever reachable
 * through the show it belongs to.
 *
 * Who may write here is settled by {@see PerformancePolicy}.
 */
class PerformanceController extends Controller
{
    /**
     * List the show's performances, soonest first, together with the groups a
     * performance may be handed to — the form offering the choice is on the
     * same page, so one round trip is enough for both.
     *
     * @return AnonymousResourceCollection<int, PerformanceResource>
     */
    public function index(Request $request, Show $show): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Performance::class, $show]);

        $performances = $show->performances()
            ->with('team')
            ->withCount('technicalPlans')
            ->orderBy('date')
            ->get();

        return PerformanceResource::collection($performances)->additional([
            'teams' => Performance::assignableTeams($request->user())
                ->map(fn (Team $team): array => ['id' => $team->id, 'name' => $team->name])
                ->values(),
        ]);
    }

    /**
     * Add a performance to the show.
     */
    public function store(SavePerformanceRequest $request, Show $show): JsonResponse
    {
        $performance = $show->performances()->create($request->performanceAttributes());

        Log::info('Performance added to a show', [
            'performance_id' => $performance->id,
            'show_id' => $show->id,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'user_id' => $request->user()->id,
        ]);

        return PerformanceResource::make($performance->load('team')->loadCount('technicalPlans'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update one of the show's performances.
     */
    public function update(SavePerformanceRequest $request, Show $show, Performance $performance): PerformanceResource
    {
        $performance->fill($request->performanceAttributes());

        $changed = array_keys($performance->getDirty());

        $performance->save();

        Log::info('Performance updated', [
            'performance_id' => $performance->id,
            'show_id' => $show->id,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'user_id' => $request->user()->id,
            'changed' => $changed,
        ]);

        return PerformanceResource::make($performance->load('team')->loadCount('technicalPlans'));
    }

    /**
     * Delete one of the show's performances. The technical plans written for it are
     * not deleted with it — they are left without a performance, the column being
     * nulled, which is why the screen warns when there are any.
     */
    public function destroy(Request $request, Show $show, Performance $performance): Response
    {
        Gate::authorize('delete', $performance);

        // The plans written for it survive without a performance, so the count
        // says how many are about to be left dangling.
        $orphanedPlans = $performance->technicalPlans()->count();

        $performance->delete();

        Log::notice('Performance deleted', [
            'performance_id' => $performance->id,
            'show_id' => $show->id,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'user_id' => $request->user()->id,
            'orphaned_plans' => $orphanedPlans,
        ]);

        return response()->noContent();
    }
}
