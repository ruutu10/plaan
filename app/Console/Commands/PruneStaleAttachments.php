<?php

namespace App\Console\Commands;

use App\Models\PendingUpload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

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
                $upload->delete();
                $pruned++;
            });

        $this->info("Pruned {$pruned} stale attachment(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
