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

    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->pending()
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
     * next one of them, and how many of the near ones nobody has handed a plan in
     * for.
     *
     * @return array{
     *     performances: int,
     *     missingPlans: int,
     *     planExpectedWithinDays: int,
     *     next: array{showName: string, teamName: string|null, date: string, startTime: string}|null,
     * }
     */
    private function upcomingSummary(): array
    {
        $next = $this->upcomingPerformances()
            ->with(['team', 'show.team'])
            ->orderBy('date')
            ->first();

        return [
            'performances' => $this->upcomingPerformances()->count(),
            'missingPlans' => $this->upcomingPerformances()
                // Nothing is expected of a night further out than this, so a
                // season booked months ahead does not sit on the dashboard as a
                // standing pile of "missing" work nobody owes yet.
                ->where('date', '<=', now()->addDays(TechnicalPlan::EXPECTED_WITHIN_DAYS))
                ->whereDoesntHave(
                    'technicalPlans',
                    fn (Builder $plans) => $plans->whereIn('status', TechnicalPlanStatus::delivered()),
                )
                ->count(),
            'planExpectedWithinDays' => TechnicalPlan::EXPECTED_WITHIN_DAYS,
            'next' => $next ? [
                'showName' => $next->show->name,
                'teamName' => $next->performerName(),
                'date' => $next->startDate(),
                'startTime' => $next->startTime(),
            ] : null,
        ];
    }

    /**
     * The performances still to come. A performance now carries its curtain-up,
     * so tonight's stays ahead until it actually starts rather than until
     * midnight. The stand-in performance is left out: it is where the plans
     * without a night of their own are filed, not an evening to count or to
     * chase a plan for.
     *
     * @return Builder<Performance>
     */
    private function upcomingPerformances(): Builder
    {
        return Performance::query()
            ->excludingPlaceholder()
            ->where('date', '>=', now());
    }

    /**
     * The plans handed in most recently, newest first.
     *
     * @return Collection<int, TechnicalPlan>
     */
    private function latestSubmittedPlans(): Collection
    {
        return TechnicalPlan::query()
            ->with(['user', 'performance.team', 'performance.show.team'])
            ->whereIn('status', TechnicalPlanStatus::delivered())
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit(self::TIMELINE_LENGTH)
            ->get();
    }
}
