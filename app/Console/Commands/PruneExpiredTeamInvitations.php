<?php

namespace App\Console\Commands;

use App\Models\TeamInvitation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('team-invitations:prune-expired')]
#[Description('Delete team invitations past their expiry date')]
class PruneExpiredTeamInvitations extends Command
{
    /**
     * Delete invitations whose expiry has passed. One left standing is a link
     * that still signs somebody into a team they were never confirmed for.
     */
    public function handle(): int
    {
        $deleted = TeamInvitation::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deleted} expired invitation(s).");

        Log::info('Pruned expired team invitations', [
            'deleted' => $deleted,
        ]);

        return self::SUCCESS;
    }
}
