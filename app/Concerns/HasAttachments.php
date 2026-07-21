<?php

namespace App\Concerns;

use App\Models\PendingUpload;
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
     * (e.g. `protected string $attachmentsCollection = 'technical-plan';`);
     * models that don't fall back to the generic `attachments` collection.
     *
     * The property is intentionally not declared on the trait: PHP forbids a
     * class from redeclaring a trait property with a different initial value,
     * which is precisely what per-model collection names require.
     */
    public function attachmentsCollection(): string
    {
        return property_exists($this, 'attachmentsCollection')
            ? $this->attachmentsCollection
            : 'attachments';
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
     * The attachments serialised for the frontend. The `url` points at the
     * app's streaming endpoint (keyed by the unguessable media UUID), not at
     * the private disk, which is not publicly reachable.
     *
     * @return array<int, array{id: string, name: string, size: int, url: string, downloadUrl: string}>
     */
    public function attachmentsPayload(): array
    {
        return $this->getMedia($this->attachmentsCollection)
            ->map(fn (Media $media): array => [
                'id' => (string) $media->uuid,
                'name' => $media->file_name,
                'size' => (int) $media->size,
                'url' => route('attachments.show', $media->uuid),
                'downloadUrl' => route('attachments.show', ['uuid' => $media->uuid, 'download' => 1]),
            ])
            ->values()
            ->all();
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
