<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            User::where('current_team_id', $team->id)
                ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
                ->each(fn (User $member) => $this->moveElsewhere($member, $team));

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();
        });
    }

    /**
     * Move a user out of the team being deleted: home to their personal team,
     * or to any other team they belong to. A user with nowhere left to go is
     * left without a current team rather than pointed at a deleted one.
     */
    private function moveElsewhere(User $member, Team $team): void
    {
        $fallback = $member->personalTeam() ?? $member->fallbackTeam($team);

        if ($fallback) {
            $member->switchTeam($fallback);

            return;
        }

        $member->update(['current_team_id' => null]);
    }
}
