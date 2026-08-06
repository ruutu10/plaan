<?php

namespace App\Models;

use Database\Factories\ClaudeReasoningLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * What Claude made of one Planka card, in its own words.
 *
 * The weekly import asks the model to say how it read each card — where the date
 * came from, why an evening became one night or three, why a group was matched
 * or left off — and this is where that answer is kept. One row per card read,
 * pointing at every record that reading produced, so the question a person
 * actually asks ("why is this format called that?") can be answered from the
 * screen the wrong record is on.
 *
 * Nothing here is written for an audience: the notes quote the card back and
 * argue with themselves, which is why reading them takes {@see VIEW_PERMISSION}.
 *
 * @property int $id
 * @property string|null $card_id
 * @property string|null $card_name
 * @property list<string> $notes
 * @property array<string, mixed>|null $raw_response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Format> $formats
 * @property-read Collection<int, Performance> $performances
 */
#[Fillable([
    'card_id',
    'card_name',
    'notes',
    'raw_response',
])]
class ClaudeReasoningLog extends Model
{
    /** @use HasFactory<ClaudeReasoningLogFactory> */
    use HasFactory;

    /**
     * The permission — held by the "technician" and "staff" roles — that opens
     * the AI's reasoning to its holder. Everyone else is not shown that a log
     * exists at all.
     */
    public const VIEW_PERMISSION = 'claude.view_log';

    /**
     * Tie a record the reading produced to this log. Doing it without detaching
     * means a card read twice links the same records once, and the unique key on
     * the pivot means a record never ends up with two explanations.
     */
    public function link(Format|Performance $subject): void
    {
        $subject->reasoningLogs()->syncWithoutDetaching([$this->getKey()]);
    }

    /**
     * The formats this reading created.
     *
     * @return MorphToMany<Format, $this>
     */
    public function formats(): MorphToMany
    {
        return $this->morphedByMany(Format::class, 'subject', 'claude_reasoning_log_subjects');
    }

    /**
     * The performances this reading created.
     *
     * @return MorphToMany<Performance, $this>
     */
    public function performances(): MorphToMany
    {
        return $this->morphedByMany(Performance::class, 'subject', 'claude_reasoning_log_subjects');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notes' => 'array',
            'raw_response' => 'array',
        ];
    }
}
