<?php

namespace App\Http\Controllers;

use App\Enums\TechnicalPlanStatus;
use App\Http\Requests\StoreTechnicalPlanRequest;
use App\Http\Resources\TechnicalPlan as TechnicalPlanResource;
use App\Models\Performance;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Services\TechnicalPlanReviewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function show(TechnicalPlan $plan): JsonResponse
    {
        return response()->json(TechnicalPlanResource::make($plan)->resolve());
    }

    /**
     * Store (or update) a plan and return its shareable token & public link.
     */
    public function store(StoreTechnicalPlanRequest $request): JsonResponse
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

        if ($submitting) {
            $attributes['status'] = TechnicalPlanStatus::Submitted;
            $attributes['submitted_at'] = now();
        } elseif (! $plan->exists) {
            $attributes['status'] = TechnicalPlanStatus::Draft;
        }

        $plan->fill($attributes)->save();

        $plan->syncAttachments($data['extra']['files'] ?? []);

        return response()->json([
            'token' => $plan->token,
            'status' => $plan->status->value,
            'publicUrl' => route('technical-plan.public', $plan),
            'files' => $plan->attachmentsPayload(),
        ]);
    }

    /**
     * List the plans the authenticated user has already submitted, so they can
     * reuse one as the basis for a new plan.
     */
    public function lookup(Request $request): JsonResponse
    {
        $results = TechnicalPlan::query()
            ->with('performance.team')
            ->where('user_id', $request->user()->id)
            ->where('status', TechnicalPlanStatus::Submitted)
            ->latest('submitted_at')
            ->limit(50)
            ->get()
            ->map(fn (TechnicalPlan $plan) => [
                'token' => $plan->token,
                'title' => trim(($plan->performance?->show_name ?: 'Nimeta plaan').($plan->performance?->team?->name ? ' — '.$plan->performance->team->name : '')),
                'sub' => collect([
                    $plan->performance?->show_date?->format('d.m.Y'),
                    $plan->submitted_at ? 'esitatud '.$plan->submitted_at->format('d.m.Y') : null,
                ])->filter()->implode(' · '),
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * List upcoming performances the user can attach a plan to.
     */
    public function performances(): JsonResponse
    {
        $results = Performance::query()
            ->with('team')
            ->whereDate('show_date', '>=', now()->toDateString())
            ->orderBy('show_date')
            ->limit(100)
            ->get()
            ->map(fn (Performance $performance) => [
                'id' => $performance->id,
                'performer' => $performance->team->name ?? '',
                'showName' => $performance->show_name,
                'showDate' => $performance->show_date->format('Y-m-d'),
                'duration' => $performance->duration,
                'description' => $performance->description ?? '',
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Ask the technician AI to review a plan and suggest improvements.
     */
    public function aiReview(StoreTechnicalPlanRequest $request): JsonResponse
    {
        if (blank(config('services.anthropic.key'))) {
            return response()->json([
                'message' => 'AI ülevaatus pole seadistatud.',
            ], 422);
        }

        try {
            $review = app(TechnicalPlanReviewer::class)
                ->review($this->planFromRequest($request->validated(), $request->user()));
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'AI ülevaatus ebaõnnestus. Proovi hetke pärast uuesti.',
            ], 422);
        }

        return response()->json([
            'review' => $review ?: 'Tagasisidet ei saadud. Proovi uuesti.',
        ]);
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
            'allowedExtensions' => array_values(array_map(
                fn (string $extension): string => strtolower($extension),
                (array) config('media-library.allowed_extensions', []),
            )),
            'maxFileSize' => (int) config('media-library.max_file_size'),
        ];
    }

    /**
     * Hydrate an unsaved plan (with its performance, team and contact user) from
     * validated wizard input, so it can be handed to the AI reviewer as a full
     * TechnicalPlan model without touching the database.
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

        $performance = new Performance([
            'show_name' => $meta['showName'] ?? null,
            'show_date' => $meta['showDate'] ?? null,
            'duration' => $meta['duration'] ?? null,
            'description' => $meta['description'] ?? null,
        ]);
        $performance->setRelation('team', new Team(['name' => $meta['performer'] ?? null]));

        $plan->setRelation('performance', $performance);
        $plan->setRelation('user', $user);

        return $plan;
    }
}
