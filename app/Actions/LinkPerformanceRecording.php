<?php

namespace App\Actions;

use App\Models\Performance;
use App\Models\PerformanceRecording;

/**
 * Say where a performance's recording is, or that there is no longer one.
 *
 * Clearing the link puts the row aside rather than destroying it, and entering
 * one again finds the same row and brings it back. That is what keeps a
 * corrected address from writing to the performers twice about the same night —
 * see {@see PerformanceRecording}, which holds the answer to whether anybody
 * has been told.
 */
class LinkPerformanceRecording
{
    /**
     * Point the performance at the given address, or at nothing when it is
     * blank.
     *
     * @return bool whether there is now a link, and it is not the one that was
     *              there before — which is to say whether anything needs
     *              pushing to Jellyfin off the back of this
     */
    public function handle(Performance $performance, ?string $url): bool
    {
        if (blank($url)) {
            $performance->recording?->delete();

            $performance->unsetRelation('recording');

            return false;
        }

        $recording = PerformanceRecording::withTrashed()
            ->firstOrNew(['performance_id' => $performance->getKey()]);

        // A link entered again after being cleared is the same recording coming
        // back, not a new one — see the class docblock.
        $restored = $recording->trashed();

        if ($restored) {
            $recording->restore();
        }

        $recording->url = $url;

        $changed = $recording->isDirty('url') || ! $recording->exists;

        $recording->save();

        $performance->setRelation('recording', $recording);

        return $changed || $restored;
    }
}
