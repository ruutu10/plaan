<?php

namespace App\Http\Resources;

use App\Models\ClaudeReasoningLog;
use App\Models\Format as FormatModel;
use App\Models\Performance as PerformanceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * One format as the management screens show it: what it is called, what it is
 * about, whose it is, and how many performances hang off it.
 *
 * @property-read FormatModel $resource
 */
class Format extends JsonResource
{
    /**
     * Transform the format into a listable and editable row.
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
     *     createdBy: string,
     *     createdAt: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $format = $this->resource;

        return [
            'id' => $format->id,
            'name' => $format->name,
            'description' => $format->description,
            'teamId' => $format->team_id,
            'teamName' => $format->team?->name,
            // Only the listing counts the performances; the edit page does not.
            'performanceCount' => $format->performances_count,
            // Opening a format and correcting it are not the same right: a group
            // that only plays an act on the evening reaches the format to correct
            // its own slot, and finds the rest read-only.
            'canEdit' => Gate::allows('update', $format),
            // How many readings stand behind this format — one per card that made
            // it or added a night to it. Zero for everyone who may not read
            // them, so the screen never offers a button the API would refuse.
            'reasoningLogCount' => $request->user()?->can(ClaudeReasoningLog::VIEW_PERMISSION)
                ? $format->reasoningLogs->count()
                : 0,
            // Where the format came from and when, both read-only: a format nobody
            // remembers entering was read off a card, and the screens say so
            // rather than leaving it to be guessed.
            'createdBy' => $format->created_by->value,
            // Already on the venue's clock, like every other moment leaving
            // here — the browser is never asked to do the arithmetic.
            'createdAt' => $format->created_at
                ?->setTimezone(PerformanceModel::venueTimezone())
                ->toIso8601String(),
        ];
    }
}
