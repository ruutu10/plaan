<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One sound file offered in the wizard's "pick a sound you have already
 * uploaded" step, named by the plan it belongs to so the performer can tell two
 * files called `tunnus.mp3` apart.
 *
 * This is the file as it stands on *another* plan: picking it copies it, so the
 * `id` here names the source, not the handle the new plan will carry.
 *
 * @property-read Media $resource
 */
class ReusableSound extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * The plan is handed in rather than read off `$media->model`: the listing
     * is built by walking the plans the user may open, so it is already to
     * hand — and asking each file for its own would be a query apiece.
     */
    public function __construct(Media $media, private TechnicalPlanModel $plan)
    {
        parent::__construct($media);
    }

    /**
     * Transform the stored file into a pickable row.
     *
     * @return array{id: string, name: string, size: int, url: string, planToken: string, planLabel: string, performanceDate: string|null}
     */
    public function toArray(Request $request): array
    {
        $media = $this->resource;
        $performance = $this->plan->performance;

        return [
            'id' => (string) $media->uuid,
            'name' => $media->file_name,
            'size' => (int) $media->size,
            // Playable straight from the picker, so a performer can listen
            // before committing a file they last used months ago.
            'url' => route('attachments.show', $media->uuid),
            'planToken' => $this->plan->token,
            'planLabel' => trim($performance?->format?->name ?: 'Nimeta plaan'),
            'performanceDate' => $performance?->startsAt()->format('d.m.Y'),
        ];
    }
}
