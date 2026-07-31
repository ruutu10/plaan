<?php

namespace App\Models;

use App\Enums\ReminderSchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The record that one of the {@see ReminderSchedule} moments of a performance
 * has been dealt with. Its existence is the whole signal — the reminder run
 * only looks at performances that have no row for the moment it is working on —
 * so a row is written whether the mail went out or the moment was written off.
 *
 * @property int $id
 * @property int $performance_id
 * @property ReminderSchedule $schedule
 * @property Carbon|null $sent_at
 * @property int $recipients
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Performance $performance
 */
#[Fillable([
    'schedule',
    'sent_at',
    'recipients',
])]
class PerformanceReminder extends Model
{
    /**
     * Whether this row stands for a mail that actually went out, as against a
     * moment written off unsent.
     */
    public function wasSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * The performance this reminder was (or was not) sent about.
     *
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule' => ReminderSchedule::class,
            'sent_at' => 'datetime',
            'recipients' => 'integer',
        ];
    }
}
