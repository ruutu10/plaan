<?php

namespace App\Http\Controllers;

use App\Console\Commands\ImportPlankaPerformances;
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
     * The overview of the performances the user may manage, soonest first.
     *
     * What comes back is decided by permission rather than by the route: a
     * technician is handed every performance in the house, whatever format it
     * belongs to and whichever group plays it, and anybody else only the nights
     * of their own groups.
     */
    public function overview(Request $request): InertiaResponse
    {
        // Soonest first: what is coming up next is what the crew looks for,
        // with already-played nights sinking toward the bottom.
        $performances = Performance::query()
            ->with(['format.team', 'team'])
            ->withCount('technicalPlans')
            ->editableBy($request->user())
            ->orderBy('date')
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
            // Counted, not loaded: the listing only says how many people staff
            // each night, and the names belong to the performance's own page.
            ->withCount(['technicalPlans', 'staff'])
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
            $performance->load(['team', 'staff', 'reasoningLogs'])->loadCount(['technicalPlans', 'staff']),
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
            $performance->setRelation('format', $format)->load('team')->loadCount(['technicalPlans', 'staff']),
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
            $performance->setRelation('format', $format)->load('team')->loadCount(['technicalPlans', 'staff']),
        );
    }

    /**
     * Delete one of the format's performances — put it aside, or wipe it outright.
     *
     * Put aside is the usual answer and the default the screen offers: the row
     * stays, so the plans written for the night keep pointing at something, and
     * the Planka import recognises the night as one the house has already dealt
     * with rather than announcing it again (see
     * {@see ImportPlankaPerformances::actsAlreadyOn()}).
     *
     * `force` asks for the other answer: the row goes, and the plans written for
     * it go with it — the database cascades them, a plan not being allowed to
     * outlive the night it describes. Nothing is left for the import to
     * recognise, so a night still on the card is registered again on the next
     * run, which is the point of asking for it.
     */
    public function destroy(Request $request, Format $format, Performance $performance): Response
    {
        Gate::authorize('delete', $performance);

        $permanently = $request->boolean('force');

        // The plans written for it survive a performance put aside, so the count
        // says how many are about to be left dangling — or, when it is wiped,
        // how many the cascade is about to take with it.
        $plans = $performance->technicalPlans()->count();

        $permanently ? $performance->forceDelete() : $performance->delete();

        Log::notice('Performance deleted', [
            'performance_id' => $performance->id,
            'format_id' => $format->id,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'user_id' => $request->user()->id,
            'orphaned_plans' => $permanently ? 0 : $plans,
            'plans_deleted' => $permanently ? $plans : 0,
            'permanently' => $permanently,
        ]);

        return response()->noContent();
    }
}
