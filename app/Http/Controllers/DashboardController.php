<?php

namespace App\Http\Controllers;

use App\Enums\TechnicalPlanStatus;
use App\Http\Resources\AdminTechnicalPlan as AdminTechnicalPlanResource;
use App\Models\Performance;
use App\Models\TeamInvitation;
use App\Models\TechnicalPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How many of the most recently handed-in plans the timeline shows.
     */
    private const TIMELINE_LENGTH = 8;

    /**
     * The statuses a performance counts as covered by: a draft is nobody's
     * plan yet, and an archived one belongs to a performance that has been and
     * gone.
     *
     * @var array<int, TechnicalPlanStatus>
     */
    private const DELIVERED_STATUSES = [
        TechnicalPlanStatus::Submitted,
        TechnicalPlanStatus::Received,
    ];

    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        // The plan timeline carries who wrote what and links straight into the
        // plans, so it is for the technical crew alone — the same permission
        // that opens the plan overview.
        $canViewAllPlans = (bool) $request->user()?->can(TechnicalPlan::VIEW_ALL_PERMISSION);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'upcoming' => $this->upcomingSummary(),
            'latestPlans' => $canViewAllPlans
                ? AdminTechnicalPlanResource::collection($this->latestSubmittedPlans())->resolve($request)
                : [],
        ]);
    }

    /**
     * What is still to be played, house-wide: how many performances are ahead, the
     * next one of them, and how many of them nobody has handed a plan in for.
     *
     * @return array{
     *     performances: int,
     *     missingPlans: int,
     *     next: array{showName: string, teamName: string|null, date: string}|null,
     * }
     */
    private function upcomingSummary(): array
    {
        $next = $this->upcomingPerformances()
            ->with('show.team')
            ->orderBy('date')
            ->first();

        return [
            'performances' => $this->upcomingPerformances()->count(),
            'missingPlans' => $this->upcomingPerformances()
                ->whereDoesntHave(
                    'technicalPlans',
                    fn (Builder $plans) => $plans->whereIn('status', self::DELIVERED_STATUSES),
                )
                ->count(),
            'next' => $next ? [
                'showName' => $next->show->name,
                'teamName' => $next->show->team?->name,
                'date' => $next->date->toDateString(),
            ] : null,
        ];
    }

    /**
     * The performances from today on. A performance is dated to the day, so the one
     * playing tonight is still ahead.
     *
     * @return Builder<Performance>
     */
    private function upcomingPerformances(): Builder
    {
        return Performance::query()->whereDate('date', '>=', now()->toDateString());
    }

    /**
     * The plans handed in most recently, newest first.
     *
     * @return Collection<int, TechnicalPlan>
     */
    private function latestSubmittedPlans(): Collection
    {
        return TechnicalPlan::query()
            ->with(['user', 'performance.show.team'])
            ->whereIn('status', self::DELIVERED_STATUSES)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit(self::TIMELINE_LENGTH)
            ->get();
    }
}
