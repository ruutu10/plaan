<?php

namespace App\Http\Controllers;

use App\Data\RecordLinks;
use App\Enums\TechnicalPlanStatus;
use App\Http\Resources\AdminTechnicalPlan as AdminTechnicalPlanResource;
use App\Http\Resources\TodaysPerformance as TodaysPerformanceResource;
use App\Models\Performance;
use App\Models\TeamInvitation;
use App\Models\TechnicalPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
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

        $todaysBill = $this->todaysPerformances();
        $next = $this->nextPerformance();
        $latestPlans = $canViewAllPlans ? $this->latestSubmittedPlans() : new Collection;

        // Every name the page shows leads back to the record behind it, so the
        // reach for all three widgets is worked out in one go rather than
        // widget by widget.
        $links = RecordLinks::for($request->user(), $todaysBill
            ->concat([$next])
            ->concat($latestPlans->map(fn (TechnicalPlan $plan) => $plan->performance)));

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'upcoming' => $this->upcomingSummary($next, $links),
            'today' => $this->todaysBill($request, $todaysBill, $links),
            'latestPlans' => $latestPlans
                ->map(fn (TechnicalPlan $plan) => AdminTechnicalPlanResource::make($plan)->linkedBy($links)->resolve($request))
                ->all(),
        ]);
    }

    /**
     * Tonight's bill: what the house is playing today, curtain-up first, each
     * with the plans handed in for it. Every performance is listed whether or
     * not a plan has come in — an evening nobody has written for is the one
     * worth seeing here — and so is every plan, though only the ones the reader
     * may open are named; see {@see TechnicalPlan::listableBy()}.
     *
     * @param  Collection<int, Performance>  $performances  today's bill, as {@see todaysPerformances()} read it
     * @return array<int, array<string, mixed>>
     */
    private function todaysBill(Request $request, Collection $performances, RecordLinks $links): array
    {
        $visiblePlanIds = $this->visiblePlanIds($request, $performances);

        return $performances
            ->map(fn (Performance $performance) => TodaysPerformanceResource::make($performance, $visiblePlanIds, $links)
                ->resolve($request))
            ->all();
    }

    /**
     * The performances on today's bill, curtain-up first, each with the plans
     * handed in for it.
     *
     * @return Collection<int, Performance>
     */
    private function todaysPerformances(): Collection
    {
        return Performance::query()
            ->excludingPlaceholder()
            ->playedToday()
            ->with([
                'team',
                'format.team',
                // A draft is not a plan anybody has handed in, so it does not
                // count as one here — its night still reads as unplanned.
                'technicalPlans' => fn (Relation $plans) => $plans
                    ->whereIn('status', TechnicalPlanStatus::delivered())
                    ->with('user')
                    ->orderBy('submitted_at')
                    ->orderBy('id'),
            ])
            ->orderBy('date')
            ->get();
    }

    /**
     * Which of the listed plans the reader may open, answered in one query
     * rather than one per plan.
     *
     * @param  Collection<int, Performance>  $performances
     * @return SupportCollection<int, int>
     */
    private function visiblePlanIds(Request $request, Collection $performances): SupportCollection
    {
        $planIds = $performances
            ->flatMap(fn (Performance $performance) => $performance->technicalPlans->modelKeys());

        if ($planIds->isEmpty()) {
            return $planIds;
        }

        return TechnicalPlan::query()
            ->whereKey($planIds)
            ->listableBy($request->user())
            ->pluck('id');
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
     *     next: array{formatName: string, formatUrl: string|null, performanceUrl: string|null, teamName: string|null, date: string, startTime: string}|null,
     * }
     */
    private function upcomingSummary(?Performance $next, RecordLinks $links): array
    {
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
                'formatName' => $next->format->name,
                // The screens behind the names, when this reader may open them.
                'formatUrl' => $links->formatUrl($next),
                'performanceUrl' => $links->performanceUrl($next),
                'teamName' => $next->performerName(),
                'date' => $next->startDate(),
                'startTime' => $next->startTime(),
            ] : null,
        ];
    }

    /**
     * The soonest performance still to come, or null when the house has nothing
     * left on the books.
     */
    private function nextPerformance(): ?Performance
    {
        return $this->upcomingPerformances()
            ->with(['team', 'format.team'])
            ->orderBy('date')
            ->first();
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
            ->with(['user', 'performance.team', 'performance.format.team'])
            ->whereIn('status', TechnicalPlanStatus::delivered())
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit(self::TIMELINE_LENGTH)
            ->get();
    }
}
