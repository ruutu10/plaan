<?php

namespace App\Console\Commands;

use App\Models\PendingUpload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('attachments:prune-stale {--hours=72 : Delete staged uploads older than this many hours}')]
#[Description('Delete staged file uploads that were never attached to a model, removing them from disk and the database.')]
class PruneStaleAttachments extends Command
{
    /**
     * Delete abandoned {@see PendingUpload} holders — files that were uploaded
     * but never moved onto their intended model. Deleting each holder fires its
     * `deleting` hook, which clears the underlying media from disk and the
     * database.
     */
    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $pruned = 0;

        // Iterate by primary key (keyset pagination) so deleting rows as we go
        // does not shift an offset and skip records — `each()`/`chunk()` would.
        PendingUpload::query()
            ->where('created_at', '<', $cutoff)
            ->lazyById()
            ->each(function (PendingUpload $upload) use (&$pruned): void {
                Log::debug('Pruning a stale staged upload', [
                    'pending_upload_id' => $upload->id,
                    'created_at' => $upload->created_at?->toIso8601String(),
                ]);

                $upload->delete();
                $pruned++;
            });

        $this->info("Pruned {$pruned} stale attachment(s) older than {$hours}h.");

        // Files leaving the disk on a schedule: the count is what tells us the
        // job is still running, and whether staged uploads are piling up.
        Log::info('Pruned stale staged uploads', [
            'pruned' => $pruned,
            'older_than_hours' => $hours,
            'cutoff' => $cutoff->toIso8601String(),
        ]);

        return self::SUCCESS;
    }
}
