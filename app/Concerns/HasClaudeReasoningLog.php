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
 * The relation is a to-many because the link table is polymorphic and shared,
 * but a unique key holds it to one: {@see reasoningLog()} is how it is read.
 *
 * @mixin Model
 */
trait HasClaudeReasoningLog
{
    /**
     * The reading that produced this record, as a relation — the form the
     * eager loading in the listing controllers needs.
     *
     * @return MorphToMany<ClaudeReasoningLog, $this>
     */
    public function reasoningLogs(): MorphToMany
    {
        return $this->morphToMany(ClaudeReasoningLog::class, 'subject', 'claude_reasoning_log_subjects');
    }

    /**
     * The reading that produced this record, or null for one entered by hand.
     */
    public function reasoningLog(): ?ClaudeReasoningLog
    {
        return $this->reasoningLogs->first();
    }
}
