<?php

namespace App\Http\Resources;

use App\Models\Performance as PerformanceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the crew's overview of every performance in the house: what is
 * played, by whom, when, and how much of it has been planned. A listing row and
 * nothing more — a performance is still corrected on the show it belongs to,
 * which is where the row's link leads.
 *
 * @property-read PerformanceModel $resource
 */
class AdminPerformance extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * Transform the performance into a listable row.
     *
     * @return array{
     *     id: int,
     *     showId: int,
     *     showName: string,
     *     title: string|null,
     *     teamName: string|null,
     *     date: string,
     *     startTime: string,
     *     duration: int|null,
     *     isDraft: bool,
     *     technicalPlanCount: int|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $performance = $this->resource;

        return [
            'id' => $performance->id,
            // The show is what the row is opened through: the performance is
            // edited there, not here.
            'showId' => $performance->show_id,
            'showName' => $performance->show->name,
            // The act's own name, for an evening several groups share; empty
            // when the show's name already says what is played.
            'title' => $performance->title,
            // Who plays it — its own group, or the show's. Never read off the
            // show directly; see Performance::performerName().
            'teamName' => $performance->performerName(),
            'date' => $performance->startDate(),
            'startTime' => $performance->startTime(),
            'duration' => $performance->duration,
            // Imported and not reviewed yet, which keeps it out of the listing
            // technical plans are written from.
            'isDraft' => $performance->is_draft,
            'technicalPlanCount' => $performance->technical_plans_count,
        ];
    }
}
