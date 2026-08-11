<?php

namespace App\Http\Resources;

use App\Models\ClaudeReasoningLog;
use App\Models\Performance as PerformanceModel;
use App\Services\PlankaClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One dated performance of a format, as the management screens list and edit it.
 *
 * @property-read PerformanceModel $resource
 */
class Performance extends JsonResource
{
    /**
     * Transform the performance into a listable and editable row.
     *
     * Every moment here travels as the UTC instant it is stored as — the
     * browser, not this array, is where it becomes the venue's own clock.
     *
     * @return array{
     *     id: int,
     *     formatId: int,
     *     formatName: string,
     *     title: string|null,
     *     teamId: int|null,
     *     teamName: string|null,
     *     startsAt: string,
     *     duration: int|null,
     *     isDraft: bool,
     *     technicalPlanCount: int|null,
     *     staffCount: int|null,
     *     reasoningLogCount: int,
     *     plankaCardId: string|null,
     *     plankaCardUrl: string|null,
     *     createdBy: string,
     *     createdAt: string|null,
     *     staff: mixed,
     * }
     */
    public function toArray(Request $request): array
    {
        $performance = $this->resource;

        return [
            'id' => $performance->id,
            // The details page opens through the format, but names it too — a
            // performance's own page is otherwise a screen with no format on it.
            'formatId' => $performance->format_id,
            'formatName' => $performance->format->name,
            // Both empty for the ordinary performance, whose format already says
            // what is played and by whom; filled for an act on an evening
            // several groups share.
            'title' => $performance->title,
            'teamId' => $performance->team_id,
            'teamName' => $performance->team?->name,
            'startsAt' => $performance->date->toIso8601String(),
            'duration' => $performance->duration,
            // Imported and not reviewed yet, which keeps it out of the listing
            // plans are written from until somebody clears it here.
            'isDraft' => $performance->is_draft,
            // Deleting a performance leaves the plans written for it behind without
            // one, so the screen warns before that happens.
            'technicalPlanCount' => $performance->technical_plans_count,
            // How many people the import last put on this performance. Counted
            // rather than listed, so a listing says how well staffed a night is
            // without carrying every name — see 'staff' below for those.
            'staffCount' => $performance->staff_count,
            // Whether the import's account of this performance can be read, for
            // whoever may read it — and zero for everyone else, so the screen
            // never offers a button the API would refuse. One card, so never
            // more than one.
            'reasoningLogCount' => $request->user()?->can(ClaudeReasoningLog::VIEW_PERMISSION)
                ? $performance->reasoningLogs->count()
                : 0,
            // The card on the board, as a field to correct and as a link to
            // follow. The link is empty when no board is configured.
            'plankaCardId' => $performance->planka_card_id,
            'plankaCardUrl' => PlankaClient::cardUrl($performance->planka_card_id),
            // Where the performance came from and when, both read-only: a date
            // nobody remembers choosing was read off a card, and the screens say
            // so rather than leaving it to be guessed.
            'createdBy' => $performance->created_by->value,
            'createdAt' => $performance->created_at?->toIso8601String(),
            // Imported and read-only: see App\Services\PerformanceStaffSync.
            // Empty unless eager-loaded, so a listing that has no use for it
            // never pays for the query.
            'staff' => PerformanceStaffMember::collection($this->whenLoaded('staff')),
        ];
    }
}
