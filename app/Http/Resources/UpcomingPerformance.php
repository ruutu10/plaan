<?php

namespace App\Http\Resources;

use App\Models\Performance;
use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A performance the user can attach a new plan to. Each row also carries the
 * user's own plans for other stagings of the same show, so the wizard can offer
 * to pre-fill from a past one.
 *
 * @property-read Performance $resource
 */
class UpcomingPerformance extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @param  Collection<int, TechnicalPlanModel>  $candidatePriorPlans  the user's plans for any of the listed shows; the ones belonging to this show's other stagings are picked out here
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
            'performer' => $performance->team->name ?? '',
            'showName' => $performance->show_name,
            'showDate' => $performance->show_date->format('Y-m-d'),
            'duration' => $performance->duration,
            'description' => $performance->description ?? '',
            'priorPlans' => PriorPlan::collection($this->priorPlans())->resolve($request),
        ];
    }

    /**
     * The user's plans written for other stagings of the same show.
     *
     * @return Collection<int, TechnicalPlanModel>
     */
    private function priorPlans(): Collection
    {
        $performance = $this->resource;

        return $this->candidatePriorPlans
            ->filter(fn (TechnicalPlanModel $plan): bool => $plan->performance !== null
                && $plan->performance->show_name === $performance->show_name
                && $plan->performance->id !== $performance->id)
            ->values();
    }
}
