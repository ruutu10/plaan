<?php

namespace App\Http\Resources;

use App\Actions\StagePlanCopy;
use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One scene of a plan. Scenes live in the plan's JSON, but a scene's sound
 * file does not — the scene only keeps the file's handle, so serialising it
 * means resolving that handle against the plan's `sound` media collection to
 * hand the wizard a full file handle (streaming and download links included).
 *
 * @property-read array<string, mixed> $resource
 */
class PlanScene extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @param  array<string, mixed>  $scene
     */
    public function __construct(array $scene, private ?Media $soundFile = null)
    {
        parent::__construct($scene);
    }

    /**
     * Serialise all of a plan's scenes, resolving each scene's sound file.
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
            fn (array $scene): array => (new self(
                $scene,
                $soundFiles->get($scene['soundFile']['id'] ?? ''),
            ))->resolve($request),
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
            'soundUrl' => TechnicalPlan::text($scene['soundUrl'] ?? null),
            'sound' => TechnicalPlan::text($scene['sound'] ?? null),
            'notes' => TechnicalPlan::text($scene['notes'] ?? null),
            // A plan that has not been saved yet (one being AI-reviewed) has no
            // stored media to resolve, so its submitted handle is kept as-is.
            'soundFile' => $this->soundFile
                ? Attachment::make($this->soundFile)->resolve($request)
                : ($scene['soundFile'] ?? null),
        ]);
    }
}
