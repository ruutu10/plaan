<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A single stored file, serialised as the handle the wizard passes around: the
 * media UUID plus the endpoints the file can be reached through. Attachments
 * live on a private disk, so the URLs point at the application's streaming
 * route, never at the disk itself.
 *
 * @property-read Media $resource
 */
class Attachment extends JsonResource
{
    /**
     * The frontend consumes handles unwrapped — both on their own (the response
     * to an upload) and nested in a plan's `extra.files`.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * The upload endpoint answers with a plain 200 — the handle is a staging
     * receipt rather than a created resource of its own.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }

    /**
     * Transform the media record into a file handle.
     *
     * @return array{id: string, name: string, size: int, url: string, downloadUrl: string}
     */
    public function toArray(Request $request): array
    {
        $media = $this->resource;

        return [
            'id' => (string) $media->uuid,
            'name' => $media->file_name,
            'size' => (int) $media->size,
            'url' => route('attachments.show', $media->uuid),
            'downloadUrl' => route('attachments.show', ['uuid' => $media->uuid, 'download' => 1]),
        ];
    }
}
