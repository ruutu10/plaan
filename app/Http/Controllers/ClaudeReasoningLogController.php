<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClaudeReasoningLog as ClaudeReasoningLogResource;
use App\Models\Performance;
use App\Models\Show;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The JSON behind the reasoning modal: what the AI made of the cards an imported
 * record came from.
 *
 * A performance comes from one card and has one reading. A show may have many —
 * an Õppelava is created by the first card that announces one and gathers a
 * night from every card after it — so both are answered with a list, newest
 * first, and the modal shows them one under the other.
 *
 * Who may ask is settled by the routes' `can:` middleware — the reasoning is a
 * debugging aid for the house's own people, not part of what a show says about
 * itself — and the management screens only offer the button to the same
 * holders, so nobody is shown a reading they would be refused.
 */
class ClaudeReasoningLogController extends Controller
{
    /**
     * Every reading that made this show what it is.
     *
     * @return AnonymousResourceCollection<int, ClaudeReasoningLogResource>
     */
    public function forShow(Show $show): AnonymousResourceCollection
    {
        return ClaudeReasoningLogResource::collection($show->reasoningLogs()->latest()->get());
    }

    /**
     * The reading that registered this performance — one card, at most one
     * reading, but answered in the same shape as a show's.
     *
     * @return AnonymousResourceCollection<int, ClaudeReasoningLogResource>
     */
    public function forPerformance(Show $show, Performance $performance): AnonymousResourceCollection
    {
        return ClaudeReasoningLogResource::collection($performance->reasoningLogs()->latest()->get());
    }
}
