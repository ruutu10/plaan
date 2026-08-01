<?php

namespace App\Concerns;

use App\Models\ClaudeReasoningLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Gives a model the AI's account of how it came to exist — see
 * {@see ClaudeReasoningLog}. Used by the records the Planka import creates, so
 * the reasoning behind a wrong name or a wrong hour can be read from the screen
 * that shows it.
 *
 * A performance is read off one card and has one account of itself. A show may
 * have many: an Õppelava is created by the first card that announces one and
 * gathers a night from every card after it, each read on its own.
 *
 * @mixin Model
 */
trait HasClaudeReasoningLog
{
    /**
     * The readings this record came out of, newest last — the order the pivot
     * gives them, which the screens re-sort for themselves.
     *
     * @return MorphToMany<ClaudeReasoningLog, $this>
     */
    public function reasoningLogs(): MorphToMany
    {
        return $this->morphToMany(ClaudeReasoningLog::class, 'subject', 'claude_reasoning_log_subjects');
    }
}
