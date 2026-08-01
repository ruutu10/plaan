<?php

namespace App\Http\Resources;

use App\Models\ClaudeReasoningLog;
use App\Models\Show as ShowModel;
use App\Services\PlankaClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * One show as the management screens show it: what it is called, what it is
 * about, whose it is, and how many performances hang off it.
 *
 * @property-read ShowModel $resource
 */
class Show extends JsonResource
{
    /**
     * Transform the show into a listable and editable row.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     description: string|null,
     *     teamId: int|null,
     *     teamName: string|null,
     *     performanceCount: int|null,
     *     canEdit: bool,
     *     reasoningLogCount: int,
     *     plankaCardId: string|null,
     *     plankaCardUrl: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $show = $this->resource;

        return [
            'id' => $show->id,
            'name' => $show->name,
            'description' => $show->description,
            'teamId' => $show->team_id,
            'teamName' => $show->team?->name,
            // Only the listing counts the performances; the edit page does not.
            'performanceCount' => $show->performances_count,
            // Opening a show and correcting it are not the same right: a group
            // that only plays an act on the evening reaches the show to correct
            // its own slot, and finds the rest read-only.
            'canEdit' => Gate::allows('update', $show),
            // How many readings stand behind this show — one per card that made
            // it or added a night to it. Zero for everyone who may not read
            // them, so the screen never offers a button the API would refuse.
            'reasoningLogCount' => $request->user()?->can(ClaudeReasoningLog::VIEW_PERMISSION)
                ? $show->reasoningLogs->count()
                : 0,
            // The card on the board, as a field to correct and as a link to
            // follow. The link is empty when no board is configured.
            'plankaCardId' => $show->planka_card_id,
            'plankaCardUrl' => PlankaClient::cardUrl($show->planka_card_id),
        ];
    }
}
