<?php

namespace App\Http\Resources;

use App\Models\Performance;
use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A performance the user can attach a new plan to. Each row also carries the
 * plans handed in for other stagings of the same show — the user's own and their
 * teams' alike — so a new plan can be pre-filled from a past one.
 *
 * @property-read Performance $resource
 */
class UpcomingPerformance extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @param  Collection<int, TechnicalPlanModel>  $candidatePriorPlans  the plans available to the user for any of the listed shows; the ones belonging to this show's other stagings are picked out here
     */
    public function __construct(Performance $performance, private Collection $candidatePriorPlans = new Collection)
    {
        parent::__construct($performance);
    }

    /**
     * Transform the performance into a selectable row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $performance = $this->resource;

        return [
            'id' => $performance->id,
            'performer' => $performance->show->team->name ?? '',
            'showName' => $performance->show->name,
            'showDate' => $performance->date->format('Y-m-d'),
            'duration' => $performance->duration,
            'description' => $performance->show->description ?? '',
            'priorPlans' => PriorPlan::collection($this->priorPlans($request))->resolve($request),
        ];
    }

    /**
     * The plans written for other stagings of the same show. A plan for this
     * very staging is left out only when it is the user's own — theirs is to be
     * edited, not cloned — while a team-mate's is offered, since taking over an
     * existing plan for the upcoming show is the point.
     *
     * @return Collection<int, TechnicalPlanModel>
     */
    private function priorPlans(Request $request): Collection
    {
        $performance = $this->resource;

        return $this->candidatePriorPlans
            ->filter(fn (TechnicalPlanModel $plan): bool => $plan->performance !== null
                && $plan->performance->show_id === $performance->show_id
                && ! ($plan->performance->id === $performance->id && $plan->user_id === $request->user()?->id))
            ->values();
    }
}
