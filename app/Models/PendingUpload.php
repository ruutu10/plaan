<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A short-lived holder for files uploaded before their owning model has been
 * saved. Media are attached here on upload and moved onto the target model on
 * save; rows left behind (abandoned uploads) can be pruned by their timestamps.
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PendingUpload extends Model implements HasMedia
{
    use HasAttachments;

    protected string $attachmentsCollection = 'pending-upload';

    /**
     * Delete the holder together with its staged media.
     */
    protected static function booted(): void
    {
        static::deleting(function (PendingUpload $upload): void {
            $upload->clearMediaCollection($upload->attachmentsCollection());
        });
    }
}
