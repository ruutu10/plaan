<?php

namespace App\Events;

use App\Models\PerformanceRecording;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Somebody said where a night's recording is — for the first time, or because
 * the address they gave before was wrong.
 *
 * Two things follow, and neither waits on the other: what this app knows about
 * the night is pushed onto the episode, and the people who wrote its technical
 * plan are told there is a video. They are separate listeners on purpose — a
 * media server that is down should not hold back a letter about a video that is
 * plainly up, and a letter that bounces should not re-push metadata.
 */
class PerformanceRecordingLinked
{
    /**
     * Both listeners are queued, so what travels is a pair of keys rather than
     * a pair of models: the push and the letter should read the recording as it
     * stands when they run, not as it stood when the button was pressed.
     */
    use Dispatchable, SerializesModels;

    public function __construct(
        public PerformanceRecording $recording,
        public User $linkedBy,
    ) {
        //
    }

    /**
     * Whether this is the first video this night has had.
     *
     * A corrected address is pushed again but announced only once: the
     * performers were told a recording of this evening exists, and that goes on
     * being true however many times the link is fixed.
     */
    public function isFirstLink(): bool
    {
        return $this->recording->announced_at === null;
    }
}
