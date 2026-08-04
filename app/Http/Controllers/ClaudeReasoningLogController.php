<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClaudeReasoningLog as ClaudeReasoningLogResource;
use App\Models\Format;
use App\Models\Performance;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The JSON behind the reasoning modal: what the AI made of the cards an imported
 * record came from.
 *
 * A performance comes from one card and has one reading. A format may have many —
 * an Õppelava is created by the first card that announces one and gathers a
 * night from every card after it — so both are answered with a list, newest
 * first, and the modal shows them one under the other.
 *
 * Who may ask is settled by the routes' `can:` middleware — the reasoning is a
 * debugging aid for the house's own people, not part of what a format says about
 * itself — and the management screens only offer the button to the same
 * holders, so nobody is shown a reading they would be refused.
 */
class ClaudeReasoningLogController extends Controller
{
    /**
     * Every reading that made this format what it is.
     *
     * @return AnonymousResourceCollection<int, ClaudeReasoningLogResource>
     */
    public function forFormat(Format $format): AnonymousResourceCollection
    {
        return ClaudeReasoningLogResource::collection($format->reasoningLogs()->latest()->get());
    }

    /**
     * The reading that registered this performance — one card, at most one
     * reading, but answered in the same shape as a format's.
     *
     * @return AnonymousResourceCollection<int, ClaudeReasoningLogResource>
     */
    public function forPerformance(Format $format, Performance $performance): AnonymousResourceCollection
    {
        return ClaudeReasoningLogResource::collection($performance->reasoningLogs()->latest()->get());
    }
}
