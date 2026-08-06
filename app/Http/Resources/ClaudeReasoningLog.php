<?php

namespace App\Http\Resources;

use App\Models\ClaudeReasoningLog as ClaudeReasoningLogModel;
use App\Services\PlankaClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One reading of a Planka card, as the screen inspecting an imported record
 * shows it.
 *
 * @property-read ClaudeReasoningLogModel $resource
 */
class ClaudeReasoningLog extends JsonResource
{
    /**
     * Transform the reading into the account the modal displays.
     *
     * @return array{
     *     id: int,
     *     cardId: string|null,
     *     cardName: string|null,
     *     cardUrl: string|null,
     *     notes: list<string>,
     *     rawResponse: array<string, mixed>|null,
     *     readAt: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource;

        return [
            'id' => $log->id,
            // The card the account is about, so the reader can go and look at
            // the text the notes keep quoting.
            'cardId' => $log->card_id,
            'cardName' => $log->card_name,
            'cardUrl' => PlankaClient::cardUrl($log->card_id),
            'notes' => $log->notes,
            // The model's answer whole, for whoever needs more than the notes
            // say — a note that turns out to be wrong is checked against this.
            'rawResponse' => $log->raw_response,
            // When the card was read, which is not when the record was last
            // touched by hand — an old reading explains less than it seems to.
            'readAt' => $log->created_at?->toIso8601String(),
        ];
    }
}
