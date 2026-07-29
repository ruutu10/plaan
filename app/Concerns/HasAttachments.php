<?php

namespace App\Concerns;

use App\Http\Resources\Attachment;
use App\Models\PendingUpload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Gives a model a generic file-attachment collection backed by Spatie Media
 * Library. Files are uploaded up front through the AttachmentController (staged
 * on a {@see PendingUpload}) and reconciled onto the model with
 * {@see self::syncAttachments()} when the parent record is saved.
 *
 * Any model that `implements HasMedia` can `use HasAttachments` to gain the
 * same upload handling — the wizard's file handles are model-agnostic.
 *
 * @mixin HasMedia
 */
trait HasAttachments
{
    use InteractsWithMedia;

    /**
     * The (private) disk attachments are stored on. Files are never served
     * directly from here — they are streamed through the AttachmentController.
     */
    public const ATTACHMENTS_DISK = 'local';

    /**
     * Purpose-specific collections this model keeps alongside its default one.
     * Consuming models override this to declare their own (e.g. a plan's scene
     * sound files).
     *
     * @return array<int, string>
     */
    protected function extraAttachmentCollections(): array
    {
        return [];
    }

    /**
     * The name of this model's default attachments collection. Consuming
     * models customise it by declaring a `$attachmentsCollection` property
     * (e.g. `protected string $attachmentsCollection = 'technical-plan';`)
     */
    public function attachmentsCollection(): string
    {
        return $this->attachmentsCollection;
    }

    /**
     * Every collection this model stores attachments in: its default one plus
     * any purpose-specific collections it declares through
     * `$extraAttachmentCollections` (e.g. a plan's scene sound files).
     *
     * @return array<int, string>
     */
    public function attachmentCollections(): array
    {
        return array_values(array_unique(array_merge(
            [$this->attachmentsCollection],
            $this->extraAttachmentCollections(),
        )));
    }

    /**
     * Register every attachments collection on the private disk.
     */
    public function registerMediaCollections(): void
    {
        foreach ($this->attachmentCollections() as $collection) {
            $this->addMediaCollection($collection)->useDisk(self::ATTACHMENTS_DISK);
        }
    }

    /**
     * This model's attachments in the given collection (its default one when
     * omitted). Serialising them for the frontend is the {@see Attachment}
     * resource's job.
     *
     * @return Collection<int, Media>
     */
    public function attachments(?string $collection = null): Collection
    {
        return $this->getMedia($collection ?? $this->attachmentsCollection)->values();
    }

    /**
     * Duplicate a collection's attachments into fresh staged uploads,
     * physically copying each file on disk. The copies are staged exactly like
     * a new upload, so the client can submit them to move them onto a new
     * model — used to carry files across when a plan is copied, without ever
     * touching the source's own media.
     *
     * @return Collection<int, Media>
     */
    public function duplicateAttachmentsToStaging(?string $collection = null): Collection
    {
        return $this->getMedia($collection ?? $this->attachmentsCollection)
            ->map(fn (Media $media): Media => $this->duplicateMediaToStaging($media))
            ->values();
    }

    /**
     * Copy a single attachment into a fresh staged upload.
     */
    public function duplicateMediaToStaging(Media $media): Media
    {
        // One holder per file: syncAttachments() deletes each staged upload
        // after moving it, so copies must not share a holder.
        $pending = PendingUpload::create();

        return $media->copy($pending, $pending->attachmentsCollection(), self::ATTACHMENTS_DISK);
    }

    /**
     * Reconcile a collection's attachments with the handles supplied by the
     * client: move newly staged uploads onto the model, keep the ones still
     * referenced, and drop any that were removed.
     *
     * Moving a staged upload re-keys it, so the submitted handles are answered
     * with a map of submitted handle => the handle the file now lives under.
     * Handles that could not be resolved are absent from the map.
     *
     * @param  array<int, array{id?: string, name?: string, size?: int}>  $files
     * @return array<string, string>
     */
    public function syncAttachments(array $files, ?string $collection = null): array
    {
        $collection ??= $this->attachmentsCollection;

        $existing = $this->getMedia($collection)->keyBy('uuid');
        $resolved = [];
        $unresolved = [];

        foreach ($files as $file) {
            $handle = $file['id'] ?? null;

            if (! $handle) {
                continue;
            }

            if ($existing->has($handle)) {
                $resolved[$handle] = $handle;

                continue;
            }

            $staged = Media::query()->where('uuid', $handle)->first();

            if ($staged && $staged->model instanceof PendingUpload) {
                $moved = $staged->move($this, $collection);
                $staged->model->delete();
                $resolved[$handle] = (string) $moved->uuid;

                continue;
            }

            $unresolved[] = $handle;
        }

        $kept = array_flip($resolved);

        $dropped = $existing
            ->reject(fn (Media $media): bool => isset($kept[$media->uuid]))
            ->each->delete();

        // A handle the client sent that resolved to nothing is a file the user
        // believes they attached and we silently did not — the one failure in
        // this flow that never surfaces in the response.
        if ($unresolved !== []) {
            Log::warning('Submitted attachment handles resolved to nothing', [
                'model_type' => $this::class,
                'model_id' => $this->getKey(),
                'collection' => $collection,
                'handles' => $unresolved,
            ]);
        }

        Log::debug('Reconciled attachments', [
            'model_type' => $this::class,
            'model_id' => $this->getKey(),
            'collection' => $collection,
            'submitted' => count($files),
            'kept' => count($resolved),
            'deleted' => $dropped->count(),
        ]);

        // The sync mutated media directly, so refresh the cached relation for
        // any follow-up read (e.g. serialising the canonical attachment list).
        $this->load('media');

        return $resolved;
    }
}
