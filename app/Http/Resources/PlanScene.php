<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
     * With `$duplicateSoundFiles` the resolved files are duplicated into fresh
     * staged uploads instead — used when the plan is opened as the basis for a
     * new one, so the copy carries its own files.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forPlan(TechnicalPlanModel $plan, Request $request, bool $duplicateSoundFiles = false): array
    {
        $soundFiles = $plan->sceneSoundFiles();

        return array_map(function (array $scene) use ($plan, $soundFiles, $duplicateSoundFiles, $request): array {
            $media = $soundFiles->get($scene['soundFile']['id'] ?? '');

            if ($media && $duplicateSoundFiles) {
                $media = $plan->duplicateMediaToStaging($media);
            }

            return (new self($scene, $media))->resolve($request);
        }, $plan->scenes);
    }

    /**
     * Transform the scene into the shape the wizard works with.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_replace($this->resource, [
            // A plan that has not been saved yet (one being AI-reviewed) has no
            // stored media to resolve, so its submitted handle is kept as-is.
            'soundFile' => $this->soundFile
                ? Attachment::make($this->soundFile)->resolve($request)
                : ($this->resource['soundFile'] ?? null),
        ]);
    }
}
