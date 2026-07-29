<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Putting a team away. The team itself is only soft-deleted — what it staged
 * keeps pointing at it — but its memberships and open invitations are cleared,
 * and anybody left standing in it is moved to a team they still have.
 */
class DeleteTeam
{
    /**
     * Delete the team, moving its members elsewhere first.
     *
     * @param  User|null  $except  A user to leave standing where they are; the
     *                             caller is expected to move them itself.
     */
    public function handle(Team $team, ?User $except = null): void
    {
        DB::transaction(function () use ($team, $except): void {
            $moved = 0;

            User::where('current_team_id', $team->id)
                ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
                ->each(function (User $member) use ($team, &$moved): void {
                    $member->sendHomeFrom($team);
                    $moved++;
                });

            $invitationsCancelled = $team->invitations()->delete();
            $membershipsCleared = $team->memberships()->delete();

            $team->delete();

            // Deleting a team takes people's memberships and open invitations
            // with it, so the counts are what makes the fallout readable after
            // the fact.
            Log::notice('Team deleted', [
                'team_id' => $team->id,
                'slug' => $team->slug,
                'is_personal' => $team->is_personal,
                'members_moved' => $moved,
                'memberships_cleared' => $membershipsCleared,
                'invitations_cancelled' => $invitationsCancelled,
                'left_standing_user_id' => $except?->id,
            ]);
        });
    }
}
