<?php

namespace App\Http\Resources;

use App\Models\Performance as PerformanceModel;
use App\Models\PerformanceRecording as PerformanceRecordingModel;
use App\Services\JellyfinClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * The video of a played night, as the screens showing that night read it.
 *
 * @property-read PerformanceRecordingModel $resource
 */
class PerformanceRecording extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     url: string,
     *     itemUrl: string|null,
     *     syncedAt: string|null,
     *     syncError: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $recording = $this->resource;

        // Whether this reader is one of the crew, asked of the night rather
        // than of the recording: the right to say where a video is and the
        // right to be told a push failed are the same right.
        $isCrew = $recording->performance instanceof PerformanceModel
            && Gate::allows('linkRecording', $recording->performance);

        return [
            // What was pasted, which is what the field holds when it is opened
            // again for correcting.
            'url' => $recording->url,
            // The same episode as the library itself addresses it, which is
            // what a link on a screen should point at: a link pasted in an odd
            // but readable shape still opens the right video. Empty when no
            // library is configured to open it on.
            'itemUrl' => JellyfinClient::itemUrl($recording->item_id),
            'syncedAt' => $recording->synced_at?->toIso8601String(),
            // Kept from everybody but the crew: a group has nothing to do with
            // the media server refusing a write, and nothing it could do about
            // it.
            'syncError' => $isCrew ? $recording->sync_error : null,
        ];
    }
}
