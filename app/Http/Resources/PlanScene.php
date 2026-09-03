<?php

namespace App\Http\Resources;

use App\Actions\StagePlanCopy;
use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One scene of a plan. Scenes live in the plan's JSON, but their sound files do
 * not — a scene only keeps each file's handle, so serialising it means
 * resolving those handles against the plan's `sound` media collection to hand
 * the wizard full file handles (streaming and download links included).
 *
 * @property-read array<string, mixed> $resource
 */
class PlanScene extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @param  array<string, mixed>  $scene
     * @param  Collection<string, Media>  $soundFiles  Every sound file the plan
     *                                                 stores, keyed by handle.
     */
    public function __construct(array $scene, private Collection $soundFiles)
    {
        parent::__construct($scene);
    }

    /**
     * Serialise all of a plan's scenes, resolving each scene's sound files.
     *
     * `$soundFiles` may instead be the staged copies made by
     * {@see StagePlanCopy}, keyed by the handle the scene still
     * names — used when the plan is opened as the basis for a new one, so the
     * copy carries its own files.
     *
     * @param  Collection<string, Media>|null  $soundFiles
     * @return array<int, array<string, mixed>>
     */
    public static function forPlan(TechnicalPlanModel $plan, Request $request, ?Collection $soundFiles = null): array
    {
        $soundFiles ??= $plan->sceneSoundFiles();

        return array_map(
            fn (array $scene): array => (new self($scene, $soundFiles))->resolve($request),
            $plan->scenes,
        );
    }

    /**
     * Transform the scene into the shape the wizard works with.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $scene = $this->resource;

        return array_replace($scene, [
            // Every scene field is optional, so a stored scene can hold nulls
            // where the wizard's `Scene` shape promises text.
            'id' => TechnicalPlan::text($scene['id'] ?? null, 'stseen-1'),
            'name' => TechnicalPlan::text($scene['name'] ?? null),
            'light' => TechnicalPlan::text($scene['light'] ?? null),
            'sound' => TechnicalPlan::text($scene['sound'] ?? null),
            'notes' => TechnicalPlan::text($scene['notes'] ?? null),
            'sounds' => $this->sounds($request),
        ]);
    }

    /**
     * The scene's cues in the order they are played, each either a link or a
     * resolved file handle.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sounds(Request $request): array
    {
        $sounds = [];

        foreach ($this->resource['sounds'] ?? [] as $position => $sound) {
            if (! is_array($sound)) {
                continue;
            }

            $handle = is_array($sound['file'] ?? null) ? $sound['file'] : null;
            $media = $this->soundFiles->get($handle['id'] ?? '');

            $sounds[] = [
                'id' => TechnicalPlan::text($sound['id'] ?? null, 'heli-'.($position + 1)),
                'url' => TechnicalPlan::text($sound['url'] ?? null),
                // A plan that has not been saved yet (one being AI-reviewed) has
                // no stored media to resolve, so its submitted handle stays as-is.
                'file' => $media ? Attachment::make($media)->resolve($request) : $handle,
            ];
        }

        return $sounds;
    }
}
