<?php

namespace App\Http\Resources;

use App\Data\RecordLinks;
use App\Models\Performance;
use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * One performance on today's bill, with the plans handed in for it. A plan the
 * reader may not open is still named — the crew needs to know a plan exists
 * before they go chasing one — but nothing of it is given away beyond that.
 *
 * @property-read Performance $resource
 */
class TodaysPerformance extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * What a plan the reader may not open is shown as: it has been handed in,
     * and that is all this reader gets to know about it.
     */
    public const HIDDEN_LABEL = 'Esitatud, peidetud';

    /**
     * The screens this reader may open behind the row's names. Left unlinked
     * when the caller worked none out.
     */
    private RecordLinks $links;

    /**
     * @param  Collection<int, int>  $visiblePlanIds  the ids of the plans this reader may open; anything outside it is shown as hidden
     */
    public function __construct(Performance $performance, private Collection $visiblePlanIds = new Collection, ?RecordLinks $links = null)
    {
        parent::__construct($performance);

        $this->links = $links ?? RecordLinks::none();
    }

    /**
     * Transform the performance into a row of tonight's bill.
     *
     * @return array{
     *     id: int,
     *     formatName: string,
     *     formatUrl: string|null,
     *     performanceUrl: string|null,
     *     title: string|null,
     *     teamName: string|null,
     *     startTime: string,
     *     plans: array<int, array{visible: bool, token: string|null, url: string|null, status: string|null, statusLabel: string, submittedBy: string|null}>,
     * }
     */
    public function toArray(Request $request): array
    {
        $performance = $this->resource;

        return [
            'id' => $performance->id,
            'formatName' => $performance->format->name,
            // The screens behind the names, when this reader may open them —
            // the row names two records, and both are corrected elsewhere.
            'formatUrl' => $this->links->formatUrl($performance),
            'performanceUrl' => $this->links->performanceUrl($performance),
            // The act's own name, when the evening is shared and the format's
            // name alone would leave three identical rows to read.
            'title' => $performance->title,
            'teamName' => $performance->performerName(),
            'startTime' => $performance->startTime(),
            'plans' => $performance->technicalPlans
                ->map(fn (TechnicalPlanModel $plan): array => $this->planEntry($plan))
                ->values()
                ->all(),
        ];
    }

    /**
     * One plan as this reader may see it: the whole row with a link into it, or
     * the bare fact that somebody has handed one in.
     *
     * @return array{visible: bool, token: string|null, url: string|null, status: string|null, statusLabel: string, submittedBy: string|null}
     */
    private function planEntry(TechnicalPlanModel $plan): array
    {
        if (! $this->visiblePlanIds->contains($plan->id)) {
            return [
                'visible' => false,
                'token' => null,
                'url' => null,
                'status' => null,
                'statusLabel' => self::HIDDEN_LABEL,
                'submittedBy' => null,
            ];
        }

        return [
            'visible' => true,
            'token' => $plan->token,
            'url' => route('technical-plan.public', $plan),
            'status' => $plan->status->value,
            'statusLabel' => $plan->status->label(),
            'submittedBy' => $plan->user?->name,
        ];
    }
}
