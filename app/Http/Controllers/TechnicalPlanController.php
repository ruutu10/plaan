<?php

namespace App\Http\Controllers;

use App\Enums\TechnicalPlanStatus;
use App\Http\Requests\StoreTechnicalPlanRequest;
use App\Http\Resources\AdminTechnicalPlan as AdminTechnicalPlanResource;
use App\Http\Resources\SavedTechnicalPlan as SavedTechnicalPlanResource;
use App\Http\Resources\TechnicalPlan as TechnicalPlanResource;
use App\Http\Resources\TechnicalPlanSummary as TechnicalPlanSummaryResource;
use App\Http\Resources\UpcomingPerformance as UpcomingPerformanceResource;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Notifications\TechnicalPlanSubmitted;
use App\Rules\AllowedAttachment;
use App\Services\TechnicalPlanReviewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class TechnicalPlanController extends Controller
{
    /**
     * Show the landing page (gate) of the technical-plan wizard.
     */
    public function index(): Response
    {
        return Inertia::render('TechnicalPlan', [
            'config' => $this->wizardConfig(),
            'initialPlan' => null,
        ]);
    }

    /**
     * List every plan in the house — drafts included — for the crew running the
     * shows. The route is closed to anyone without
     * {@see TechnicalPlan::VIEW_ALL_PERMISSION}.
     */
    public function overview(Request $request): Response
    {
        // Newest performance first: what is coming up (or has just been played) is
        // what the crew looks for. Plans not tied to a performance have no date
        // to sort by and land at the end, newest of those first.
        $plans = TechnicalPlan::query()
            ->with(['user', 'performance.show.team'])
            ->leftJoin('performances', 'performances.id', '=', 'technical_plans.performance_id')
            ->orderByDesc('performances.date')
            ->orderByDesc('technical_plans.created_at')
            ->select('technical_plans.*')
            ->get();

        return Inertia::render('technical-plans/Index', [
            'plans' => AdminTechnicalPlanResource::collection($plans)->resolve($request),
        ]);
    }

    /**
     * Open a previously shared plan as the basis for a new plan.
     */
    public function public(TechnicalPlan $plan): Response
    {
        return Inertia::render('TechnicalPlan', [
            'config' => $this->wizardConfig(),
            'initialPlan' => TechnicalPlanResource::make($plan)->resolve(),
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
    public function copy(TechnicalPlan $plan, Request $request): TechnicalPlanResource
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

        return TechnicalPlanResource::make($plan)->withDuplicatedAttachments();
    }

    /**
     * Store (or update) a plan and return its shareable token & public link.
     */
    public function store(StoreTechnicalPlanRequest $request): SavedTechnicalPlanResource
    {
        $data = $request->validated();
        $submitting = (bool) ($data['submit'] ?? false);

        $user = $request->user();

        $attributes = [
            'user_id' => $user->id,
            'performance_id' => $data['meta']['performanceId'] ?? null,
            'sound' => $data['sound'],
            'scenes' => array_values($data['scenes']),
            'equipment' => array_merge($data['equipment'], ['items' => array_values($data['equipment']['items'] ?? [])]),
            'extra' => ['notes' => $data['extra']['notes'] ?? ''],
        ];

        $plan = TechnicalPlan::query()
            ->firstOrNew(['token' => $data['token'] ?? null]);

        // The token is handed out by the public share link, so anyone holding
        // it could otherwise post over the plan behind it. Writing to a plan
        // that already exists is held to the same rule as opening it, and its
        // owner is settled at creation — a later save never reassigns it.
        if ($plan->exists) {
            if (! $plan->isVisibleTo($user)) {
                // The token travels in a public share link, so a refusal here
                // is somebody writing to a plan they only ever received a link
                // to. Worth seeing.
                Log::warning('Refused a write to a plan the user may not open', [
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ]);

                abort(403);
            }

            unset($attributes['user_id']);
        }

        if ($submitting) {
            $attributes['status'] = TechnicalPlanStatus::Submitted;
            $attributes['submitted_at'] = now();
        } elseif (! $plan->exists) {
            $attributes['status'] = TechnicalPlanStatus::Draft;
        }

        $wasNew = ! $plan->exists;

        $plan->fill($attributes)->save();

        $plan->syncAttachments($data['extra']['files'] ?? []);
        $plan->syncSceneSoundFiles();

        Log::info($submitting ? 'Technical plan submitted' : 'Technical plan saved as a draft', [
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'performance_id' => $plan->performance_id,
            'status' => $plan->status->value,
            'created' => $wasNew,
            'scenes' => count($plan->scenes),
            'attachments' => count($data['extra']['files'] ?? []),
        ]);

        // Only once the files are in place does the plan mail out complete —
        // the notification links to the plan's stored attachments.
        if ($submitting) {
            $this->notifySubmission($plan);
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
            ->with('performance.show.team')
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
     * carries the plans handed in for the same show's other performances, so a new
     * plan can be pre-filled from a past one. Those are not only the user's own:
     * a plan for a performance of one of their teams counts too, which is how
     * the next plan for a show can be written by someone else in the group than
     * the one who sent the last.
     *
     * Drafts are left out: a performance the import guessed at is not one to
     * write a plan for until an admin has vouched for it.
     */
    public function performances(Request $request): JsonResponse
    {
        $upcoming = Performance::query()
            ->with('show.team')
            ->vouchedFor()
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit(100)
            ->get();

        $priorPlans = TechnicalPlan::query()
            ->with(['performance', 'user'])
            ->visibleTo($request->user())
            ->whereIn('status', [TechnicalPlanStatus::Submitted, TechnicalPlanStatus::Received])
            ->whereHas('performance', fn ($query) => $query->whereIn('show_id', $upcoming->pluck('show_id')->all()))
            ->latest('submitted_at')
            ->limit(25)
            ->get();

        $results = $upcoming->map(
            fn (Performance $performance): array => UpcomingPerformanceResource::make($performance, $priorPlans)->resolve($request),
        );

        return response()->json(['results' => $results]);
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
     * Mail the submitted plan out: the performer keeps a copy of what they
     * sent, and the technical team gets the plan they will run the show from.
     * Resubmitting notifies again — the plan the team holds has to be the
     * current one.
     */
    private function notifySubmission(TechnicalPlan $plan): void
    {
        $notification = new TechnicalPlanSubmitted($plan);

        $plan->user?->notify($notification);

        $techEmail = (string) config('technical_plan.tech_email');
        $notifiedTech = $techEmail !== '' && $techEmail !== $plan->user?->email;

        if ($notifiedTech) {
            Notification::route('mail', $techEmail)->notify($notification);
        }

        // A submitted plan the technical team never received is the failure
        // that costs a show, so who was mailed is recorded either way.
        Log::info('Mailed out a submitted plan', [
            'plan_id' => $plan->id,
            'notified_owner' => $plan->user !== null,
            'notified_tech' => $notifiedTech,
        ]);

        if ($techEmail === '') {
            Log::warning('No technical contact configured; a submitted plan reached nobody but its author', [
                'plan_id' => $plan->id,
            ]);
        }
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
        ];
    }

    /**
     * Hydrate an unsaved plan (with its performance, show, team and contact
     * user) from validated wizard input, so it can be handed to the AI reviewer
     * as a full TechnicalPlan model without touching the database.
     *
     * @param  array<string, mixed>  $data
     */
    private function planFromRequest(array $data, User $user): TechnicalPlan
    {
        $meta = $data['meta'];

        $plan = new TechnicalPlan([
            'status' => TechnicalPlanStatus::Draft,
            'sound' => $data['sound'],
            'scenes' => array_values($data['scenes']),
            'equipment' => array_merge($data['equipment'], ['items' => array_values($data['equipment']['items'] ?? [])]),
            'extra' => ['notes' => $data['extra']['notes'] ?? ''],
        ]);

        $show = new Show([
            'name' => $meta['showName'] ?? null,
            'description' => $meta['description'] ?? null,
        ]);
        $show->setRelation('team', new Team(['name' => $meta['performer'] ?? null]));

        $performance = new Performance([
            'date' => $meta['showDate'] ?? null,
            'duration' => $meta['duration'] ?? null,
        ]);
        $performance->setRelation('show', $show);

        $plan->setRelation('performance', $performance);
        $plan->setRelation('user', $user);

        return $plan;
    }
}
