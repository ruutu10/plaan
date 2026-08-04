<?php

namespace App\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Keeps an audit trail of a model's major properties: what changed, and who
 * changed it — the signed-in user, or the system itself when nobody was
 * signed in to do it (a console import, a queued job).
 *
 * A model using this only has to say which of its own attributes are worth
 * that trail; see {@see activityLogAttributes()}.
 */
trait LogsModelActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->activityLogAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityDescription($eventName));
    }

    /**
     * The attributes worth keeping an audit trail of.
     *
     * @return array<int, string>
     */
    abstract protected function activityLogAttributes(): array;

    /**
     * What happened, and who did it.
     */
    protected function activityDescription(string $eventName): string
    {
        $actor = auth()->user();

        return sprintf(
            '%s %s by %s',
            Str::headline(class_basename($this)),
            $eventName,
            // @phpstan-ignore-next-line nullsafe.neverNull
            $actor?->name ?? 'the system',
        );
    }
}
