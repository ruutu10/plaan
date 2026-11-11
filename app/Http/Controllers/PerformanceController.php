<?php

namespace App\Http\Controllers;

use App\Actions\LinkPerformanceRecording;
use App\Console\Commands\ImportPlankaPerformances;
use App\Data\RecordLinks;
use App\Enums\PerformanceStaffRole;
use App\Enums\TechnicalPlanStatus;
use App\Events\PerformancePlotSummaryWritten;
use App\Events\PerformanceRecordingLinked;
use App\Http\Requests\Performances\SavePerformanceRequest;
use App\Http\Resources\AdminPerformance as AdminPerformanceResource;
use App\Http\Resources\AdminTechnicalPlan as AdminTechnicalPlanResource;
use App\Http\Resources\Performance as PerformanceResource;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use App\Models\Team;
use App\Models\TechnicalPlan;
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
            ->with(['format.team', 'team', 'technicians'])
            // A draft has not been handed to the crew yet, so it does not count
            // as the night's plan having been sent.
            ->withExists(['technicalPlans as has_sent_technical_plan' => fn ($plans) => $plans
                ->whereIn('status', TechnicalPlanStatus::reusable())])
            ->listableBy($user)
            ->orderBy('date')
            ->get();

        // A new performance is added from this overview only by whoever may add
        // one to any format in the house — everybody else already has their own
        // way in, through the format itself. The choice of format and group is
        // fetched here rather than left to the dialog, so opening it costs
        // nothing beyond the button press.
        $canAddToAnyFormat = $user->can(Performance::EDIT_ALL_PERMISSION);

        $links = RecordLinks::for($user, $performances);

        return Inertia::render('admin/performances/Index', [
            // A row leads to the performance's own page for whoever may open
            // it — not only the crew, but the night's own staff and the group
            // that plays it — so the reach is worked out per row, in one go.
            'performances' => $performances
                ->map(fn (Performance $performance) => AdminPerformanceResource::make($performance)
                    ->linkedBy($links)
                    ->resolve($request))
                ->all(),
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
     * Return a single performance, together with its staff, the groups it may
     * be handed to, the people a technical-plan reminder may be sent to, the
     * plans handed in for it, and the jobs its staff may be added in — the details
     * screen's own edit form needs the same choice {@see index()} offers, its
     * reminder dialog needs the group's members, its plan table the plans, its
     * staff list the jobs to pick from, and one round trip is
     * enough for all of them.
     *
     * The page is also opened by the night's staff, who may read it but not
     * change it — see {@see PerformancePolicy::view()}. `can` says which
     * reader this is, so the screen offers only the buttons the API would
     * honour, and what only the edit and reminder dialogs need — the groups,
     * the group's members — is left out for a reader who has neither.
     */
    public function show(Request $request, Format $format, Performance $performance): PerformanceResource
    {
        Gate::authorize('view', $performance);

        $user = $request->user();
        $canEdit = $user->can('update', $performance);

        return PerformanceResource::make(
            $performance->load(['team', 'staff', 'reasoningLogs', 'recording'])->loadCount(['technicalPlans', 'staff']),
        )->additional([
            'can' => [
                'edit' => $canEdit,
                // The page links back to its format, which working one night of
                // it does not open.
                'openFormat' => $user->can('view', $format),
            ],
            'teams' => $canEdit
                ? Performance::assignableTeams($user)
                    ->map(fn (Team $team): array => ['id' => $team->id, 'name' => $team->name])
                    ->values()
                : [],
            'reminderRecipients' => $canEdit ? $this->reminderRecipients($performance) : [],
            'technicalPlans' => AdminTechnicalPlanResource::collection($this->sentPlans($request->user(), $performance))
                ->resolve($request),
            ...$this->staffOptions($request->user(), $performance),
        ]);
    }

    /**
     * The jobs somebody may be put on this night's staff in by hand — the
     * staff list's own add dialog offers the choice. Empty for a reader who may
     * not change the night, so the screen offers no dialog the API would
     * refuse.
     *
     * The people to choose from are not here: the dialog searches for them by
     * name as it is typed into ({@see PerformanceStaffController::search()}),
     * so the house's accounts are never handed over in one answer.
     *
     * @return array{staffRoles: array<int, array{value: string, label: string}>}
     */
    private function staffOptions(User $user, Performance $performance): array
    {
        if (! $user->can('update', $performance)) {
            return ['staffRoles' => []];
        }

        return [
            'staffRoles' => collect(PerformanceStaffRole::cases())
                ->sortBy(fn (PerformanceStaffRole $role): int => $role->listingOrder())
                ->map(fn (PerformanceStaffRole $role): array => ['value' => $role->value, 'label' => $role->label()])
                ->values()
                ->all(),
        ];
    }

    /**
     * The plans handed in for this performance that the user may read, newest
     * first. Drafts are left out: nobody has been asked to read one yet, the
     * same reason the plans overview hides them by default.
     *
     * @return Collection<int, TechnicalPlan>
     */
    private function sentPlans(User $user, Performance $performance): Collection
    {
        return $performance->technicalPlans()
            ->with(['user', 'performance.team', 'performance.format.team', 'performance.technicians'])
            ->listableBy($user)
            ->where('status', '!=', TechnicalPlanStatus::Draft)
            ->latest()
            ->get();
    }

    /**
     * Who this performance's plan may be chased with — see
     * {@see Performance::reminderCandidates()}: the group playing it, and
     * everybody the card names on its staff. Empty when there is nobody,
     * which is what leaves the screen's reminder button switched off.
     *
     * `staffedAsPerformer` says whether the Planka card cast this person as an
     * esineja that night, which is who the reminder dialog offers ticked. A
     * group is more than the people on stage, and it is the people on stage who
     * owe the plan. `roles` names every job the card gives them, so a name
     * that is in no group still says why it is on the list.
     *
     * Names only, no addresses: the screen picks people, and their e-mail is
     * the server's business — it is where the letter goes, not something the
     * page needs to hold or show.
     *
     * @return Collection<int, array{id: int, name: string, staffedAsPerformer: bool, roles: list<string>}>
     */
    private function reminderRecipients(Performance $performance): Collection
    {
        $roles = $this->rolesOf($performance);

        return $performance->reminderCandidates()
            ->sortBy(fn (User $member): string => mb_strtolower($member->name))
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'staffedAsPerformer' => in_array(PerformanceStaffRole::Performer, $roles[$member->id] ?? [], strict: true),
                'roles' => array_map(
                    fn (PerformanceStaffRole $role): string => $role->label(),
                    $roles[$member->id] ?? [],
                ),
            ])
            ->values()
            ->toBase();
    }

    /**
     * The jobs this performance's card gives each person it names, by their
     * id, the stage first. Empty for a night nothing was imported for, which
     * is a night the screen ticks nobody on.
     *
     * @return array<int, list<PerformanceStaffRole>>
     */
    private function rolesOf(Performance $performance): array
    {
        $roles = [];

        foreach ($performance->staff as $member) {
            /** @var PerformanceStaff $staffing */
            $staffing = $member->getRelation('pivot');

            $roles[$member->id][] = $staffing->role;
        }

        foreach ($roles as &$held) {
            usort($held, fn (PerformanceStaffRole $a, PerformanceStaffRole $b): int => $a->listingOrder() <=> $b->listingOrder());
        }

        return $roles;
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
    public function update(
        SavePerformanceRequest $request,
        Format $format,
        Performance $performance,
        LinkPerformanceRecording $linkRecording,
    ): PerformanceResource {
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

        // What happens off the back of a night getting a video — the push to the
        // library, the word to whoever wrote its plan — is between the event and
        // its listeners. A link cleared, or one saved unchanged, says nothing.
        $linked = $request->saysAnythingAboutRecording()
            && $linkRecording->handle($performance, $request->recordingUrl());

        // Written after the link, which clears a summary that was the previous
        // video's — so a corrected link and its corrected summary can travel in
        // one save — and before anything is pushed, so the push carries it.
        $summaryChanged = $request->saysAnythingAboutPlotSummary()
            && $this->correctPlotSummary($performance, $request->plotSummary(), $request->user());

        if ($linked) {
            PerformanceRecordingLinked::dispatch($performance->recording, $request->user());
        } elseif ($summaryChanged) {
            PerformancePlotSummaryWritten::dispatch($performance->recording, $request->user());
        }

        return PerformanceResource::make(
            $performance->setRelation('format', $format)
                ->load(['team', 'recording'])
                ->loadCount(['technicalPlans', 'staff']),
        );
    }

    /**
     * Put the crew's own wording of the plot summary on the night's recording.
     * Nothing to correct on a night with no recording.
     *
     * @return bool whether the summary changed
     */
    private function correctPlotSummary(Performance $performance, ?string $summary, User $user): bool
    {
        $recording = $performance->recording;

        if ($recording === null) {
            return false;
        }

        $recording->plot_summary = $summary;

        if (! $recording->isDirty('plot_summary')) {
            return false;
        }

        $recording->save();

        activity()
            ->performedOn($recording)
            ->causedBy($user)
            ->event('plot_summary_edited')
            ->log(sprintf('Plot summary edited by %s', $user->name));

        return true;
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
