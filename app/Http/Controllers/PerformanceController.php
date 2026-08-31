<?php

namespace App\Http\Controllers;

use App\Console\Commands\ImportPlankaPerformances;
use App\Enums\PerformanceStaffRole;
use App\Http\Requests\Performances\SavePerformanceRequest;
use App\Http\Resources\AdminPerformance as AdminPerformanceResource;
use App\Http\Resources\Performance as PerformanceResource;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use App\Models\Team;
use App\Models\User;
use App\Policies\PerformancePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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
 * Who may write here is settled by {@see PerformancePolicy}, which hands the
 * holders of {@see Performance::EDIT_ALL_PERMISSION} the whole house and
 * everybody else their own groups' nights. How far a reader sees is a wider
 * question, settled by {@see Performance::scopeListableBy()} and its own
 * {@see Performance::VIEW_ALL_PERMISSION}.
 */
class PerformanceController extends Controller
{
    /**
     * The overview of the performances the user may read, soonest first.
     *
     * What comes back is decided by permission rather than by the route: a
     * holder of {@see Performance::VIEW_ALL_PERMISSION} is handed every
     * performance in the house, whatever format it belongs to and whichever group
     * plays it, and anybody else only the nights of their own groups. Correcting
     * one of them is a further right, checked where the change is made.
     */
    public function overview(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Soonest first: what is coming up next is what the crew looks for,
        // with already-played nights sinking toward the bottom.
        $performances = Performance::query()
            ->with(['format.team', 'team'])
            ->withCount('technicalPlans')
            ->listableBy($user)
            ->orderBy('date')
            ->get();

        // A new performance is added from this overview only by whoever may add
        // one to any format in the house — everybody else already has their own
        // way in, through the format itself. The choice of format and group is
        // fetched here rather than left to the dialog, so opening it costs
        // nothing beyond the button press.
        $canAddToAnyFormat = $user->can(Performance::EDIT_ALL_PERMISSION);

        return Inertia::render('admin/performances/Index', [
            'performances' => AdminPerformanceResource::collection($performances)->resolve($request),
            'formats' => $canAddToAnyFormat
                ? Format::query()
                    ->where('name', '!=', Format::PLACEHOLDER_NAME)
                    ->orderByRaw('LOWER(formats.name)')
                    ->get()
                    ->map(fn (Format $format): array => ['id' => $format->id, 'name' => $format->name])
                    ->values()
                : [],
            'teams' => $canAddToAnyFormat
                ? Performance::assignableTeams($user)
                    ->map(fn (Team $team): array => ['id' => $team->id, 'name' => $team->name])
                    ->values()
                : [],
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
     * Return a single performance, together with the staff imported for it, the
     * groups it may be handed to, and the people a technical-plan reminder may
     * be sent to — the details screen's own edit form needs the same choice
     * {@see index()} offers, its reminder dialog needs the group's members, and
     * one round trip is enough for all three.
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
            'reminderRecipients' => $this->reminderRecipients($performance),
        ]);
    }

    /**
     * Who this performance's plan may be chased with: the members of the group
     * playing it — its own when the evening is shared, the format's otherwise.
     * Empty when no group owns the night, which is what leaves the screen's
     * reminder button switched off.
     *
     * `staffedAsPerformer` says whether the Planka card cast this member as an
     * esineja that night, which is who the reminder dialog offers ticked. A
     * group is more than the people on stage, and it is the people on stage who
     * owe the plan.
     *
     * Names only, no addresses: the screen picks people, and their e-mail is
     * the server's business — it is where the letter goes, not something the
     * page needs to hold or show.
     *
     * @return Collection<int, array{id: int, name: string, staffedAsPerformer: bool}>
     */
    private function reminderRecipients(Performance $performance): Collection
    {
        $members = $performance->performedBy()?->members;

        if ($members === null) {
            return new Collection;
        }

        $cast = $this->castOf($performance);

        return $members
            ->sortBy(fn (User $member): string => mb_strtolower($member->name))
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'staffedAsPerformer' => in_array($member->id, $cast, strict: true),
            ])
            ->values()
            ->toBase();
    }

    /**
     * The ids of the people this performance's card names as esinejad. Empty
     * for a night nothing was imported for, which is a night the screen ticks
     * nobody on.
     *
     * @return list<int>
     */
    private function castOf(Performance $performance): array
    {
        return array_values($performance->staff
            ->filter(function (User $member): bool {
                /** @var PerformanceStaff $staffing */
                $staffing = $member->getRelation('pivot');

                return $staffing->role === PerformanceStaffRole::Performer;
            })
            ->map(fn (User $member): int => $member->id)
            ->all());
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
