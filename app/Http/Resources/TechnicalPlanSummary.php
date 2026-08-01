<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A one-line description of a plan the user has already submitted, as listed in
 * the wizard's "reuse an earlier plan" picker.
 *
 * @property-read TechnicalPlanModel $resource
 */
class TechnicalPlanSummary extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * Transform the plan into a listable row.
     *
     * @return array{token: string, title: string, sub: string}
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;
        $show = $plan->performance?->show;
        $performer = $plan->performance?->performerName();

        return [
            'token' => $plan->token,
            'title' => trim(($show?->name ?: 'Nimeta plaan').($performer ? ' — '.$performer : '')),
            'sub' => collect([
                $plan->performance?->startsAt()->format('d.m.Y'),
                $plan->submitted_at ? 'esitatud '.$plan->submitted_at->format('d.m.Y') : null,
            ])->filter()->implode(' · '),
        ];
    }
}
