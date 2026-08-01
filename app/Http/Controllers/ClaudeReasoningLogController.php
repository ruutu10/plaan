<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClaudeReasoningLog as ClaudeReasoningLogResource;
use App\Models\ClaudeReasoningLog;
use Illuminate\Http\Request;

/**
 * The JSON behind the reasoning modal: what the AI made of the card an imported
 * record came from.
 *
 * Who may ask is settled by the route's `can:` middleware — the log is a
 * debugging aid for the house's own people, not part of what a show says about
 * itself — and the management screens only offer the button to the same
 * holders, so nobody is shown a log they would be refused.
 */
class ClaudeReasoningLogController extends Controller
{
    public function __invoke(Request $request, ClaudeReasoningLog $log): ClaudeReasoningLogResource
    {
        return ClaudeReasoningLogResource::make($log);
    }
}
