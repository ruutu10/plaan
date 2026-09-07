<?php

namespace App\Http\Controllers;

use App\Actions\SaveTechnicalPlan;
use App\Actions\Sso\AttemptSilentAuthentikLogin;
use App\Actions\StagePlanCopy;
use App\Data\PlanContent;
use App\Enums\TechnicalPlanStatus;
use App\Events\TechnicalPlanStatusChanged;
use App\Events\TechnicalPlanSubmitted;
use App\Http\Requests\StoreTechnicalPlanRequest;
use App\Http\Requests\UpdateTechnicalPlanStatusRequest;
use App\Http\Resources\AdminTechnicalPlan as AdminTechnicalPlanResource;
use App\Http\Resources\Attachment as AttachmentResource;
use App\Http\Resources\ReusableSound as ReusableSoundResource;
use App\Http\Resources\SavedTechnicalPlan as SavedTechnicalPlanResource;
use App\Http\Resources\TechnicalPlan as TechnicalPlanResource;
use App\Http\Resources\TechnicalPlanSummary as TechnicalPlanSummaryResource;
use App\Http\Resources\UpcomingPerformance as UpcomingPerformanceResource;
use App\Models\Format;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Rules\AllowedAttachment;
use App\Services\TechnicalPlanReviewer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TechnicalPlanController extends Controller
{
    /**
     * How many past plans a format offers as a starting point. More than a
     * handful is not a choice, it is a list to read through.
     */
    private const PRIOR_PLANS_PER_FORMAT = 5;

    /**
     * How far back the "pick a sound you already have" listing reaches, and how
     * many sounds it may offer in the end. A performer picking a cue is looking
     * for something they used recently; a longer list is not a better one.
     */
    private const REUSABLE_SOUND_PLANS = 50;

    private const REUSABLE_SOUNDS = 100;

    /**
     * The last step of the wizard, counting from zero — the review page. Kept
     * in step with `stepComponents` in resources/js/pages/TechnicalPlan.vue,
     * and only used to hold a linked step to a page that exists.
     */
    private const LAST_STEP = 6;

    /**
     * Show the landing page (gate) of the technical-plan wizard. A guest
     * with an active Authentik session is redirected there first and
     * signed in silently, skipping the e-mail step below entirely.
     */
    public function index(Request $request, AttemptSilentAuthentikLogin $attemptSsoLogin): Response|HttpResponse
    {
        // This page (not the dashboard) is where a successful login — silent
        // or via the e-mail step below — should return the guest to.
        if (! $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        $performance = $this->linkedPerformance($request);

        return $attemptSsoLogin->handle($request) ?? Inertia::render('TechnicalPlan', [
            'config' => $this->wizardConfig(),
            'initialPlan' => null,
            // Writing a plan at all takes an account: every endpoint the wizard
            // saves through sits behind the login.
            'canEdit' => $request->user() !== null,
            // Set when the wizard was reached from a reminder's link, which
            // names the night it is about — see App\Actions\BuildTechnicalPlanInvite.
            'initialPerformance' => $performance,
            'initialStep' => $performance === null ? 0 : $this->linkedStep($request),
        ]);
    }

    /**
     * List the plans that have been written — drafts included — as far as the
     * reader reaches: every plan in the house for the crew running the formats,
     * and otherwise the reader's own plans and their groups'. See
     * {@see TechnicalPlan::listableBy()}.
     */
    public function overview(Request $request): Response
    {
        // Newest performance first: what is coming up (or has just been played)
        // is what the crew looks for. The plans filed under the stand-in
        // performance are dated years out and so gather at the top, which is
        // where the crew wants them — those are the ones needing a real night.
        $plans = TechnicalPlan::query()
            ->with(['user', 'performance.team', 'performance.format.team'])
            ->listableBy($request->user())
            ->leftJoin('performances', 'performances.id', '=', 'technical_plans.performance_id')
            ->orderByDesc('performances.date')
            ->orderByDesc('technical_plans.created_at')
            ->select('technical_plans.*')
            ->get();

        return Inertia::render('technical-plans/Index', [
            'plans' => AdminTechnicalPlanResource::collection($plans)->resolve($request),
            'statuses' => TechnicalPlanStatus::options(),
        ]);
    }

    /**
     * Show one plan's details — the same fields as the overview's row, plus
     * when it was submitted. Reaches as far as the overview does, so a row that
     * was listed always opens; see {@see TechnicalPlan::listableBy()}. Changing
     * the status from here is a separate right, guarded on its own route.
     */
    public function showDetails(Request $request, TechnicalPlan $plan): Response
    {
        if (! $plan->isListableBy($request->user())) {
            Log::warning('Refused the details of a plan the user may not read', [
                'plan_id' => $plan->id,
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        $plan->load(['user', 'performance.team', 'performance.format.team']);

        return Inertia::render('technical-plans/Show', [
            'plan' => AdminTechnicalPlanResource::make($plan)->resolve($request),
            'statuses' => TechnicalPlanStatus::options(),
        ]);
    }

    /**
     * Move a plan to a different status from its details page. The route is
     * closed to anyone without {@see TechnicalPlan::EDIT_ALL_PERMISSION}.
     */
    public function updateStatus(UpdateTechnicalPlanStatusRequest $request, TechnicalPlan $plan): RedirectResponse
    {
        $newStatus = TechnicalPlanStatus::from($request->validated('status'));
        $previousStatus = $plan->status;

        $plan->update(['status' => $newStatus]);

        // What happens off the back of a status change — mailing the author
        // once a plan is received, and whatever else joins it later — is
        // between the event and its listeners, not this controller's concern.
        TechnicalPlanStatusChanged::dispatch($plan, $previousStatus, $newStatus, $request->user());

        Log::notice('Technical plan status changed from its details page', [
            'plan_id' => $plan->id,
            'from_status' => $previousStatus->value,
            'to_status' => $newStatus->value,
            'changed_by' => $request->user()->id,
        ]);

        return to_route('technical-plans.show', $plan);
    }

    /**
     * Open a plan by its share link. The link is what a plan's author sends
     * out for other people to read, so it opens without an account — on the
     * review page, as a document. Editing it takes a login; see
     * {@see TechnicalPlan::isEditableBy()} for who gets one.
     */
    public function public(Request $request, TechnicalPlan $plan): Response
    {
        $user = $request->user();

        // A guest reading a shared plan who then logs in belongs back at the
        // plan, not at the dashboard — the same reasoning as index() above.
        if (! $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('TechnicalPlan', [
            'config' => $this->wizardConfig(),
            'initialPlan' => TechnicalPlanResource::make($plan)->resolve(),
            // The visitor is standing on the plan's own URL, so they hold its
            // key — what is left to settle is whether they are signed in.
            'canEdit' => $user !== null && $plan->isEditableBy($user, $plan->token),
            // A shared plan carries its own night and opens at the beginning.
            'initialPerformance' => null,
            // The plan is already filled in, so the link opens on the review
            // page rather than making the visitor click through every step.
            'initialStep' => self::LAST_STEP,
        ]);
    }

    /**
     * Return a saved plan's payload as JSON (open by key).
     */
    public function show(TechnicalPlan $plan): TechnicalPlanResource
    {
        return TechnicalPlanResource::make($plan);
    }

    /**
     * Duplicate a plan the user may reuse as the basis for a new one: return
     * its content together with fresh staged copies of its attachments (files
     * duplicated on disk), so submitting the new plan carries the files over
     * without affecting the source.
     */
    public function copy(TechnicalPlan $plan, Request $request, StagePlanCopy $stageCopy): TechnicalPlanResource
    {
        if (! $plan->isVisibleTo($request->user())) {
            Log::warning('Refused to copy a plan the user may not open', [
                'plan_id' => $plan->id,
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        Log::info('Plan copied as the basis for a new one', [
            'plan_id' => $plan->id,
            'user_id' => $request->user()->id,
        ]);

        return TechnicalPlanResource::make($plan)->withStagedCopy($stageCopy->handle($plan));
    }

    /**
     * The sound files the user could reuse on the plan they are writing:
     * everything stored against a plan they may open, less the plan being
     * edited — the wizard already holds that one's cues in its own state and
     * offers them without asking the server.
     */
    public function sounds(Request $request): JsonResponse
    {
        $exclude = $request->string('exclude')->toString();
        $allowed = AllowedAttachment::extensionsFor(TechnicalPlan::SOUND_COLLECTION);

        // Asked from the plans' side rather than the media's: a sound is only
        // ever offered as "the one from that plan", so the plan is what the
        // listing is built out of — and each file's own is then already to hand.
        $plans = TechnicalPlan::query()
            ->visibleTo($request->user())
            // Only the sound collection is eager-loaded: a plan's general
            // attachments are no use here, and some plans carry a lot of them.
            ->with([
                'media' => fn ($media) => $media->where('collection_name', TechnicalPlan::SOUND_COLLECTION),
                'performance.format',
            ])
            ->latest('id')
            ->limit(self::REUSABLE_SOUND_PLANS);

        if ($exclude !== '') {
            $plans->where('token', '!=', $exclude);
        }

        $sounds = $plans->get()
            ->flatMap(fn (TechnicalPlan $plan): SupportCollection => $plan
                ->attachments(TechnicalPlan::SOUND_COLLECTION)
                // Belt and braces: a plan's sound collection is already held to
                // the audio allowlist on the way in, twice over.
                ->filter(fn (Media $media): bool => in_array(strtolower($media->extension), $allowed, true))
                ->map(fn (Media $media): array => ReusableSoundResource::make($media, $plan)->resolve($request)))
            // The limit above counts plans, not sounds; this is what the picker
            // is actually handed, and it is a list somebody has to read.
            ->take(self::REUSABLE_SOUNDS);

        return response()->json(['results' => $sounds->values()->all()]);
    }

    /**
     * Stage a copy of a sound file the user already has on another plan, so the
     * plan being written can carry it as its own.
     *
     * The source plan is left untouched — the copy is a fresh staged upload,
     * moved onto this plan on save like any other file. That is what keeps two
     * plans from sharing one file's lifetime, where dropping a cue from one
     * would take the sound out from under the other.
     */
    public function reuseSound(Request $request, string $uuid): AttachmentResource
    {
        $media = Media::query()
            ->where('uuid', $uuid)
            ->where('collection_name', TechnicalPlan::SOUND_COLLECTION)
            ->first();

        $plan = $media?->model instanceof TechnicalPlan ? $media->model : null;
        $allowed = AllowedAttachment::extensionsFor(TechnicalPlan::SOUND_COLLECTION);

        if (! $media || ! $plan || ! in_array(strtolower($media->extension), $allowed, true)) {
            abort(404);
        }

        if (! $plan->isVisibleTo($request->user())) {
            Log::warning('Refused to reuse a sound file from a plan the user may not open', [
                'plan_id' => $plan->id,
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        return AttachmentResource::make($plan->duplicateMediaToStaging($media));
    }

    /**
     * Store (or update) a plan and return its shareable token & public link.
     */
    public function store(
        StoreTechnicalPlanRequest $request,
        SaveTechnicalPlan $save,
    ): SavedTechnicalPlanResource {
        $data = $request->validated();
        $submitting = (bool) ($data['submit'] ?? false);
        $user = $request->user();

        $key = $data['token'] ?? null;

        $plan = TechnicalPlan::query()
            ->firstOrNew(['token' => $key]);

        // Holding a plan's key is what lets somebody work on it — sharing the
        // link is how its author hands that out — and so is the team whose
        // night the plan is for. The wizard always posts the key it opened
        // with, so it is that half of the rule which answers here; the guard
        // is what stops a caller reaching an existing plan any other way.
        if ($plan->exists && ! $plan->isEditableBy($user, $key)) {
            Log::warning('Refused a write to a plan the user may not edit', [
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        // Someone other than the author working on a plan is the share link
        // being used as intended, but it is still the kind of thing worth
        // being able to look up afterwards.
        if ($plan->exists && $plan->user_id !== $user->id) {
            Log::info('A plan is being saved by someone other than its author', [
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'owner_id' => $plan->user_id,
                'ip' => $request->ip(),
            ]);
        }

        // Read before the save overwrites it: whether the technical team was
        // already holding this plan is what decides if submitting mails again.
        $previousStatus = $plan->exists ? $plan->status : null;

        $plan = $save->handle($plan, $data, $user, $submitting);

        // Only once the files are in place does the plan mail out complete —
        // the notification links to the plan's stored attachments.
        if ($submitting) {
            TechnicalPlanSubmitted::dispatch($plan, $previousStatus);
        }

        return SavedTechnicalPlanResource::make($plan);
    }

    /**
     * List the plans the authenticated user has already submitted, so they can
     * reuse one as the basis for a new plan.
     */
    public function lookup(Request $request): JsonResponse
    {
        $plans = TechnicalPlan::query()
            ->with(['performance.team', 'performance.format.team'])
            ->where('user_id', $request->user()->id)
            ->where('status', TechnicalPlanStatus::Submitted)
            ->latest('submitted_at')
            ->limit(50)
            ->get();

        return response()->json([
            'results' => TechnicalPlanSummaryResource::collection($plans)->resolve($request),
        ]);
    }

    /**
     * List upcoming performances the user can attach a plan to. Each row also
     * carries the plans handed in for the same format's other performances, so a new
     * plan can be pre-filled from a past one. Those are not only the user's own:
     * a plan for a performance of one of their teams counts too, which is how
     * the next plan for a format can be written by someone else in the group than
     * the one who sent the last. Archived plans are offered too: a format that has
     * been played is exactly the one whose plan the next run starts from.
     *
     * Drafts are left out: a performance the import guessed at is not one to
     * write a plan for until an admin has vouched for it.
     */
    public function performances(Request $request): JsonResponse
    {
        $upcoming = Performance::query()
            ->with(['team', 'format.team'])
            ->vouchedFor()
            ->excludingPlaceholder()
            // Still to come. A performance carries its curtain-up now, so
            // tonight's stays on offer right up until it starts.
            ->where('date', '>=', now())
            ->orderBy('date')
            ->limit(100)
            ->get();

        // Offered beside the list rather than in it: it is not a night anybody
        // is playing, and it has to stay on offer whatever else is coming up,
        // since a plan can be written no other way when the real performance is
        // not on the books.
        $placeholder = Performance::placeholder()->load(['team', 'format.team']);

        // Kept per format rather than as one global list: a single busy format would
        // otherwise fill a shared limit and leave every other row offering no
        // prior plan at all. The set is bounded by the upcoming performances
        // above, so it stays small without a limit of its own.
        $formatIds = $upcoming->pluck('format_id')->push($placeholder->format_id)->all();

        $priorPlans = TechnicalPlan::query()
            ->with(['performance', 'user'])
            ->visibleTo($request->user())
            ->whereIn('status', TechnicalPlanStatus::reusable())
            ->whereHas('performance', fn ($query) => $query->whereIn('format_id', $formatIds))
            ->latest('submitted_at')
            ->get()
            ->groupBy(fn (TechnicalPlan $plan): int => $plan->performance->format_id)
            ->flatMap(fn (Collection $plans): Collection => $plans->take(self::PRIOR_PLANS_PER_FORMAT));

        $results = $upcoming->map(
            fn (Performance $performance): array => UpcomingPerformanceResource::make($performance, $priorPlans)->resolve($request),
        );

        return response()->json([
            'results' => $results,
            'placeholder' => UpcomingPerformanceResource::make($placeholder, $priorPlans)->resolve($request),
        ]);
    }

    /**
     * Ask the technician AI to review a plan and suggest improvements.
     */
    public function aiReview(StoreTechnicalPlanRequest $request): JsonResponse
    {
        if (blank(config('services.anthropic.key'))) {
            Log::warning('AI review asked for while the integration is unconfigured', [
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'AI ülevaatus pole seadistatud.',
            ], 422);
        }

        try {
            $review = app(TechnicalPlanReviewer::class)
                ->review($this->planFromRequest($request->validated(), $request->user()));
        } catch (\Throwable $exception) {
            report($exception);

            Log::error('AI review failed', [
                'user_id' => $request->user()->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'AI ülevaatus ebaõnnestus. Proovi hetke pärast uuesti.',
            ], 422);
        }

        return response()->json([
            'review' => $review ?: 'Tagasisidet ei saadud. Proovi uuesti.',
        ]);
    }

    /**
     * The night a reminder's link named, as the wizard's own performance meta —
     * or null when the link named none, or named one that can no longer be
     * written for.
     *
     * A linked performance is held to exactly what the picker on the first step
     * offers: vouched for, and still to be played. That keeps a link from an
     * old mail from quietly selecting a night that has since been put back to
     * draft or already happened; the wizard simply opens at the beginning
     * instead, with the list to choose from.
     *
     * @return array<string, mixed>|null
     */
    private function linkedPerformance(Request $request): ?array
    {
        $id = (int) $request->query('performance');

        if ($id <= 0) {
            return null;
        }

        $performance = Performance::query()
            ->with(['team', 'format.team'])
            ->vouchedFor()
            ->where('date', '>', now())
            ->find($id);

        if ($performance === null) {
            // Reminders are the only thing handing these links out, so a link
            // that resolves to nothing is a mail outliving its performance —
            // harmless, but it explains a performer arriving at a blank wizard.
            Log::info('A technical-plan link named a performance that can no longer be written for', [
                'performance_id' => $id,
                'user_id' => $request->user()?->id,
            ]);

            return null;
        }

        return [
            'performanceId' => $performance->id,
            'performer' => $performance->performerName() ?? '',
            'formatName' => $performance->format->name,
            'performanceDate' => $performance->startDate(),
            'startTime' => $performance->startTime(),
            'duration' => $performance->duration,
            'description' => $performance->format->description ?? '',
        ];
    }

    /**
     * The step a link asks the wizard to open on, held to the steps there are.
     * Only meaningful alongside a performance — a link that has chosen the
     * night is the only reason to start anywhere but the beginning.
     */
    private function linkedStep(Request $request): int
    {
        return max(0, min(self::LAST_STEP, (int) $request->query('step')));
    }

    /**
     * Static configuration passed to the wizard frontend.
     *
     * @return array<string, mixed>
     */
    private function wizardConfig(): array
    {
        return [
            'deadlineHours' => (int) config('technical_plan.deadline_hours', 24),
            'techEmail' => (string) config('technical_plan.tech_email', 'ando@ruutu10.ee'),
            'allowedExtensions' => AllowedAttachment::extensionsFor(),
            'soundExtensions' => AllowedAttachment::extensionsFor(TechnicalPlan::SOUND_COLLECTION),
            'maxFileSize' => (int) config('media-library.max_file_size'),
            // The scene-cue limits the request rules hold a plan to, so the
            // wizard can stop short of them rather than posting a plan the
            // server will refuse.
            'maxSoundsPerScene' => StoreTechnicalPlanRequest::MAX_SOUNDS_PER_SCENE,
            'maxSoundUrlLength' => StoreTechnicalPlanRequest::MAX_SOUND_URL_LENGTH,
            // The halls where smoke is off the table, so the wizard can leave
            // the question out rather than offering a choice the venue refuses.
            'smokeNotPossible' => array_values((array) config('technical_plan.smoke_not_possible', [])),
        ];
    }

    /**
     * Hydrate an unsaved plan (with its performance, format, team and contact
     * user) from validated wizard input, so it can be handed to the AI reviewer
     * as a full TechnicalPlan model without saving anything.
     *
     * The night is loaded rather than rebuilt from what the wizard posted: the
     * format, the group, the date and the running time belong to the performance,
     * so the reviewer reads the same ones the saved plan would show. The rules
     * have already held the id to a performance that exists.
     *
     * @param  array<string, mixed>  $data
     */
    private function planFromRequest(array $data, User $user): TechnicalPlan
    {
        $plan = new TechnicalPlan(PlanContent::fromValidated($data) + [
            'status' => TechnicalPlanStatus::Draft,
        ]);

        $plan->setRelation('performance', Performance::query()
            ->with(['team', 'format.team'])
            ->findOrFail($data['meta']['performanceId']));
        $plan->setRelation('user', $user);

        return $plan;
    }
}
