<?php

namespace App\Actions;

use App\Models\TechnicalPlan;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Copy a plan's files aside so they can seed a new plan: the attachments and
 * every scene's sound cue are duplicated into fresh staged uploads, which the
 * copy then carries as its own without touching the plan they came from.
 *
 * This is a write, which is why it happens here rather than while the plan is
 * being serialised — a resource that copies files on disk as a side effect of
 * being read is a surprise waiting for the next caller.
 */
class StagePlanCopy
{
    /**
     * @return array{files: Collection<int, Media>, sceneSoundFiles: Collection<string, Media>}
     */
    public function handle(TechnicalPlan $plan): array
    {
        return [
            'files' => $plan->duplicateAttachmentsToStaging(),
            // Keyed by the handle the scene still names, so each scene can find
            // the copy that replaces its own file.
            'sceneSoundFiles' => $plan->sceneSoundFiles()
                ->map(fn (Media $media): Media => $plan->duplicateMediaToStaging($media)),
        ];
    }
}
