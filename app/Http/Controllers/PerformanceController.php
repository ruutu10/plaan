<?php

namespace App\Http\Controllers;

use App\Http\Requests\Performances\SavePerformanceRequest;
use App\Http\Resources\AdminPerformance as AdminPerformanceResource;
use App\Http\Resources\Performance as PerformanceResource;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Policies\PerformancePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Dated performances of a format: the JSON API the management screens read and
 * write them through, and the house-wide overview {@see overview()} renders.
 * Every writing route is nested under the format and its bindings are scoped to
 * it, so a performance is only ever changed through the format it belongs to.
 *
 * Who may write here is settled by {@see PerformancePolicy}; how far a reader
 * sees is settled by {@see Performance::scopeEditableBy()}, which hands the
 * holders of {@see Performance::EDIT_ALL_PERMISSION} the whole house and
 * everybody else their own groups' nights.
 */
class PerformanceController extends Controller
{
    /**
     * The overview of the performances the user may manage, newest first.
     *
     * What comes back is decided by permission rather than by the route: a
     * technician is handed every performance in the house, whatever format it
     * belongs to and whichever group plays it, and anybody else only the nights
     * of their own groups.
     */
    public function overview(Request $request): InertiaResponse
    {
        // Newest first, like the plan overview: what is coming up — or has just
        // been played — is what the crew looks for, not the archive.
        $performances = Performance::query()
            ->with(['format.team', 'team'])
            ->withCount('technicalPlans')
            ->editableBy($request->user())
            ->orderByDesc('date')
            ->get();

        return Inertia::render('admin/performances/Index', [
            'performances' => AdminPerformanceResource::collection($performances)->resolve($request),
        ]);
    }

    /**
     * List the format's performances, soonest first, together with the groups a
     * performance may be handed to — the form offering the choice is on the
     * same page, so one round trip is enough for both.
     *
     * @return AnonymousResourceCollection<int, PerformanceResource>
     */
    public function index(Request $request, Format $format): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Performance::class, $format]);

        $performances = $format->performances()
            // The reading that registered each performance rides along, for the
            // same reason the formats listing carries it: one query, not one a row.
            ->with(['team', 'reasoningLogs'])
            ->withCount('technicalPlans')
            ->orderBy('date')
            ->get()
            // Every row's format is the one already in hand — set rather than
            // asked for again, so the resource's formatName costs nothing extra
            // here.
            ->each(fn (Performance $performance) => $performance->setRelation('format', $format));

        return PerformanceResource::collection($performances)->additional([
            'teams' => Performance::assignableTeams($request->user())
                ->map(fn (Team $team): array => ['id' => $team->id, 'name' => $team->name])
                ->values(),
        ]);
    }

    /**
     * Return a single performance, together with the staff imported for it and
     * the groups it may be handed to — the details screen's own edit form needs
     * the same choice {@see index()} offers, and one round trip is enough for
     * both.
     */
    public function show(Request $request, Format $format, Performance $performance): PerformanceResource
    {
        Gate::authorize('view', $performance);

        return PerformanceResource::make(
            $performance->load(['team', 'staff', 'reasoningLogs'])->loadCount('technicalPlans'),
        )->additional([
            'teams' => Performance::assignableTeams($request->user())
                ->map(fn (Team $team): array => ['id' => $team->id, 'name' => $team->name])
                ->values(),
        ]);
    }

    /**
     * Add a performance to the format.
     */
    public function store(SavePerformanceRequest $request, Format $format): JsonResponse
    {
        $performance = $format->performances()->create($request->performanceAttributes());

        Log::info('Performance added to a format', [
            'performance_id' => $performance->id,
            'format_id' => $format->id,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'user_id' => $request->user()->id,
        ]);

        return PerformanceResource::make(
            $performance->setRelation('format', $format)->load('team')->loadCount('technicalPlans'),
        )
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update one of the format's performances.
     */
    public function update(SavePerformanceRequest $request, Format $format, Performance $performance): PerformanceResource
    {
        $performance->fill($request->performanceAttributes());

        $changed = array_keys($performance->getDirty());

        $performance->save();

        Log::info('Performance updated', [
            'performance_id' => $performance->id,
            'format_id' => $format->id,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'user_id' => $request->user()->id,
            'changed' => $changed,
        ]);

        return PerformanceResource::make(
            $performance->setRelation('format', $format)->load('team')->loadCount('technicalPlans'),
        );
    }

    /**
     * Delete one of the format's performances. The technical plans written for it are
     * not deleted with it — they are left without a performance, the column being
     * nulled, which is why the screen warns when there are any.
     */
    public function destroy(Request $request, Format $format, Performance $performance): Response
    {
        Gate::authorize('delete', $performance);

        // The plans written for it survive without a performance, so the count
        // says how many are about to be left dangling.
        $orphanedPlans = $performance->technicalPlans()->count();

        $performance->delete();

        Log::notice('Performance deleted', [
            'performance_id' => $performance->id,
            'format_id' => $format->id,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'user_id' => $request->user()->id,
            'orphaned_plans' => $orphanedPlans,
        ]);

        return response()->noContent();
    }
}
