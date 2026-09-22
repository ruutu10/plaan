<?php

namespace App\Http\Resources;

use App\Data\RecordLinks;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One night the reader has a job on, as their own strip of the bill lists it:
 * what was played, when, and what they were doing there.
 *
 * The roles come off the staffing rows, which the caller must have narrowed to
 * this one reader — see {@see Performance::staffings()}. A person can hold more
 * than one job on a night, so the row carries all of them rather than picking
 * one.
 *
 * @property-read Performance $resource
 */
class StaffedPerformance extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * The screens this reader may open behind the row's names. Left unlinked
     * when the caller worked none out.
     */
    private RecordLinks $links;

    public function __construct(Performance $performance, ?RecordLinks $links = null)
    {
        parent::__construct($performance);

        $this->links = $links ?? RecordLinks::none();
    }

    /**
     * Transform the performance into a row of the reader's own bill.
     *
     * @return array{
     *     id: int,
     *     formatName: string,
     *     formatUrl: string|null,
     *     performanceUrl: string|null,
     *     title: string|null,
     *     location: string|null,
     *     teamName: string|null,
     *     startsAt: string,
     *     roles: array<int, array{role: string, label: string}>,
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
            'location' => $performance->location,
            'teamName' => $performance->performerName(),
            'startsAt' => $performance->date->toIso8601String(),
            'roles' => $performance->staffings
                // The stage first, then the side of it — and in the same order
                // whatever the database; see {@see PerformanceStaffRole::listingOrder()}.
                ->sortBy(fn (PerformanceStaff $staffing): int => $staffing->role->listingOrder())
                ->map(fn (PerformanceStaff $staffing): array => [
                    'role' => $staffing->role->value,
                    'label' => $staffing->role->label(),
                ])
                ->values()
                ->all(),
        ];
    }
}
