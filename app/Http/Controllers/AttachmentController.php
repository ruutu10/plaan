<?php

namespace App\Http\Controllers;

use App\Concerns\HasAttachments;
use App\Models\PendingUpload;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;

/**
 * Handles ad-hoc file uploads that are staged before their owning model exists.
 * Uploads are attached to a throwaway {@see PendingUpload} and later moved onto
 * the real model via {@see HasAttachments::syncAttachments()}, so
 * this controller is not tied to any single model.
 */
class AttachmentController extends Controller
{
    /**
     * Store a single uploaded file server-side and return a handle the client
     * sends back with its form data once the owning record is saved.
     */
    public function store(Request $request): JsonResponse
    {
        $maxKilobytes = (int) (config('media-library.max_file_size') / 1024);

        $allowed = array_map(
            fn (string $extension): string => strtolower($extension),
            (array) config('media-library.allowed_extensions', []),
        );

        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                $this->allowedMimeTypeRule($allowed),
            ],
        ]);

        $file = $request->file('file');

        if ($validator->fails() || ! $file) {
            return response()->json([
                'message' => 'Faili üleslaadimine ebaõnnestus.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($allowed !== [] && ! in_array(strtolower($file->getClientOriginalExtension()), $allowed, true)) {
            return response()->json([
                'message' => 'Seda failitüüpi ei saa üles laadida.',
            ], 422);
        }

        $media = PendingUpload::create()
            ->addMedia($file)
            ->toMediaCollection(PendingUpload::ATTACHMENTS_COLLECTION);

        return response()->json([
            'id' => (string) $media->uuid,
            'name' => $media->file_name,
            'size' => (int) $media->size,
            'url' => route('attachments.show', $media->uuid),
            'downloadUrl' => route('attachments.show', ['uuid' => $media->uuid, 'download' => 1]),
        ]);
    }

    /**
     * A validation rule asserting the file's real, content-sniffed MIME type is
     * one that an allowed extension can legitimately produce — so a script that
     * has merely been renamed (e.g. `shell.jpg`) is rejected on its content, not
     * just its name. Allowed extensions Symfony has no MIME mapping for (such as
     * `.qlc`) cannot be content-checked and are left to the extension allowlist.
     *
     * @param  array<int, string>  $allowedExtensions
     */
    private function allowedMimeTypeRule(array $allowedExtensions): Closure
    {
        $mimeTypes = MimeTypes::getDefault();

        $allowedMimeTypes = [];

        foreach ($allowedExtensions as $extension) {
            $allowedMimeTypes = array_merge($allowedMimeTypes, $mimeTypes->getMimeTypes($extension));
        }

        return function (string $attribute, mixed $value, Closure $fail) use ($mimeTypes, $allowedMimeTypes): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $extension = strtolower($value->getClientOriginalExtension());

            if ($mimeTypes->getMimeTypes($extension) === []) {
                return;
            }

            if (! in_array($value->getMimeType(), $allowedMimeTypes, true)) {
                $fail('Faili sisu ei vasta lubatud failitüübile.');
            }
        };
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

        return $disk->response($path, $media->file_name, [
            'Content-Type' => $media->mime_type,
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
