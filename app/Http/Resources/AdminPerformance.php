<?php

namespace App\Http\Resources;

use App\Data\RecordLinks;
use App\Models\Performance as PerformanceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the crew's overview of every performance in the house: what is
 * played, by whom, when, and how much of it has been planned. A listing row and
 * nothing more — a performance is corrected on its own page, which is where the
 * row's link leads.
 *
 * @property-read PerformanceModel $resource
 */
class AdminPerformance extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * The screens this reader may open behind the row. Left unlinked when the
     * caller worked none out. Set through {@see linkedBy()} rather than the
     * constructor, which {@see JsonResource::collection()} calls with the row's
     * index.
     */
    private ?RecordLinks $links = null;

    /**
     * Lead the row to the performance's own page where this reader may open
     * it, for a caller that worked out what the reader reaches.
     */
    public function linkedBy(RecordLinks $links): self
    {
        $this->links = $links;

        return $this;
    }

    /**
     * Transform the performance into a listable row.
     *
     * @return array{
     *     id: int,
     *     formatId: int,
     *     performanceUrl: string|null,
     *     formatName: string,
     *     title: string|null,
     *     location: string|null,
     *     teamName: string|null,
     *     technicians: array<int, string>,
     *     startsAt: string,
     *     status: string,
     *     hasSentTechnicalPlan: bool,
     * }
     */
    public function toArray(Request $request): array
    {
        $performance = $this->resource;

        return [
            'id' => $performance->id,
            // The format the performance's own page is scoped to, which the row
            // needs to build that link.
            'formatId' => $performance->format_id,
            // The performance's own page, when this reader may open it — whoever
            // may edit the night and whoever works it, whatever their job; see
            // Performance::viewableBy().
            'performanceUrl' => ($this->links ?? RecordLinks::none())->performanceUrl($performance),
            'formatName' => $performance->format->name,
            // The act's own name, for an evening several groups share; empty
            // when the format's name already says what is played.
            'title' => $performance->title,
            'location' => $performance->location,
            // Who plays it — its own group, or the format's. Never read off the
            // format directly; see Performance::performerName().
            'teamName' => $performance->performerName(),
            'technicians' => $performance->technicianNames(),
            'startsAt' => $performance->date->toIso8601String(),
            // A draft is imported and not reviewed yet, which keeps it out of
            // the listing technical plans are written from; an archived night
            // has been played.
            'status' => $performance->status->value,
            'hasSentTechnicalPlan' => (bool) $performance->has_sent_technical_plan,
        ];
    }
}
