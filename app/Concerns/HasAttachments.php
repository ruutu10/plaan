<?php

namespace App\Concerns;

use App\Http\Resources\Attachment;
use App\Models\PendingUpload;
use Illuminate\Support\Collection;
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
     * The name of this model's attachments collection. Consuming models
     * customise it by declaring a `$attachmentsCollection` property
     * (e.g. `protected string $attachmentsCollection = 'technical-plan';`)
     */
    public function attachmentsCollection(): string
    {
        return $this->attachmentsCollection;
    }

    /**
     * Register the attachments collection on the private disk.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection($this->attachmentsCollection)
            ->useDisk(self::ATTACHMENTS_DISK);
    }

    /**
     * This model's attachments. Serialising them for the frontend is the
     * {@see Attachment} resource's job.
     *
     * @return Collection<int, Media>
     */
    public function attachments(): Collection
    {
        return $this->getMedia($this->attachmentsCollection)->values();
    }

    /**
     * Duplicate this model's attachments into fresh staged uploads, physically
     * copying each file on disk. The copies are staged exactly like a new
     * upload, so the client can submit them to move them onto a new model —
     * used to carry files across when a plan is copied, without ever touching
     * the source's own media.
     *
     * @return Collection<int, Media>
     */
    public function duplicateAttachmentsToStaging(): Collection
    {
        return $this->getMedia($this->attachmentsCollection)
            ->map(function (Media $media): Media {
                // One holder per file: syncAttachments() deletes each staged
                // upload after moving it, so copies must not share a holder.
                $pending = PendingUpload::create();

                return $media->copy($pending, $pending->attachmentsCollection(), self::ATTACHMENTS_DISK);
            })
            ->values();
    }

    /**
     * Reconcile this model's attachments with the handles supplied by the
     * client: move newly staged uploads onto the model, keep the ones still
     * referenced, and drop any that were removed.
     *
     * @param  array<int, array{id?: string, name?: string, size?: int}>  $files
     */
    public function syncAttachments(array $files): void
    {
        $existing = $this->getMedia($this->attachmentsCollection)->keyBy('uuid');
        $keep = [];

        foreach ($files as $file) {
            $handle = $file['id'] ?? null;

            if (! $handle) {
                continue;
            }

            if ($existing->has($handle)) {
                $keep[$handle] = true;

                continue;
            }

            $staged = Media::query()->where('uuid', $handle)->first();

            if ($staged && $staged->model instanceof PendingUpload) {
                $moved = $staged->move($this, $this->attachmentsCollection);
                $staged->model->delete();
                $keep[$moved->uuid] = true;
            }
        }

        $existing
            ->reject(fn (Media $media): bool => isset($keep[$media->uuid]))
            ->each->delete();

        // The sync mutated media directly, so refresh the cached relation for
        // any follow-up read (e.g. serialising the canonical attachment list).
        $this->load('media');
    }
}
