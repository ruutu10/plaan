<?php

namespace App\Http\Controllers;

use App\Concerns\HasAttachments;
use App\Http\Resources\Attachment as AttachmentResource;
use App\Models\PendingUpload;
use App\Rules\AllowedAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles ad-hoc file uploads that are staged before their owning model exists.
 * Uploads are attached to a throwaway {@see PendingUpload} and later moved onto
 * the real model via {@see HasAttachments::syncAttachments()}, so
 * this controller is not tied to any single model.
 *
 * Storing and discarding require an authenticated user (see routes/web.php);
 * streaming a stored file does not, so a publicly shared plan stays readable.
 */
class AttachmentController extends Controller
{
    /**
     * Store a single uploaded file server-side and return a handle the client
     * sends back with its form data once the owning record is saved.
     *
     * An upload may name the collection it is destined for (`collection`), in
     * which case it is held to that collection's own, narrower allowlist — a
     * scene's sound file accepts audio only.
     */
    public function store(Request $request): AttachmentResource|JsonResponse
    {
        $maxKilobytes = (int) (config('media-library.max_file_size') / 1024);
        $collection = $request->string('collection')->toString() ?: null;

        $validator = Validator::make($request->all(), [
            'collection' => ['nullable', 'string', Rule::in(AllowedAttachment::collections())],
            'file' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                new AllowedAttachment($collection),
            ],
        ]);

        $file = $request->file('file');

        if ($validator->fails() || ! $file) {
            return response()->json([
                'message' => 'Faili üleslaadimine ebaõnnestus.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pending = PendingUpload::create();

        $media = $pending
            ->addMedia($file)
            ->toMediaCollection($pending->attachmentsCollection());

        return AttachmentResource::make($media);
    }

    /**
     * Stream an attachment from the private disk by its UUID. The file is never
     * exposed on a public disk, so this route is the only way to reach it; each
     * access is written to the application log.
     *
     * With `?download=1` the file is served as an attachment (forcing a
     * download under its original name); otherwise it is served inline so the
     * browser can display it in a new tab.
     */
    public function show(Request $request, string $uuid): StreamedResponse
    {
        $media = Media::query()->where('uuid', $uuid)->firstOrFail();

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        $forceDownload = $request->boolean('download');

        Log::info('Attachment downloaded', [
            'uuid' => $media->uuid,
            'media_id' => $media->id,
            'file_name' => $media->file_name,
            'size' => $media->size,
            'model_type' => $media->model_type,
            'model_id' => $media->model_id,
            'disposition' => $forceDownload ? 'attachment' : 'inline',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // FilesystemAdapter::response() streams the file via readStream(), so
        // this works uniformly across local and non-local (e.g. S3) disks
        // without buffering the whole file into memory. We pass the stored
        // Content-Type and Content-Length explicitly so a non-local disk is
        // not hit with extra metadata round-trips to derive them.
        return $disk->response($path, $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Content-Length' => $media->size,
            'X-Content-Type-Options' => 'nosniff',
        ], $forceDownload
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE);
    }

    /**
     * Discard a staged upload that has not yet been attached to a saved model.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $media = Media::query()->where('uuid', $uuid)->first();

        if ($media && $media->model instanceof PendingUpload) {
            $media->model->delete();
        }

        return response()->json(['ok' => true]);
    }
}
