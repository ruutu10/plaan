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
                    $this->moveElsewhere($member, $team);
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

    /**
     * Move a user out of the team being deleted: home to their personal team,
     * or to any other team they belong to. A user with nowhere left to go is
     * left without a current team rather than pointed at a deleted one.
     */
    private function moveElsewhere(User $member, Team $team): void
    {
        $fallback = $member->personalTeam() ?? $member->fallbackTeam($team);

        if ($fallback) {
            Log::info('Moved a member out of a team being deleted', [
                'user_id' => $member->id,
                'from_team_id' => $team->id,
                'to_team_id' => $fallback->id,
            ]);

            $member->switchTeam($fallback);

            return;
        }

        // The user lands on the app with no team at all; the screens have to
        // cope, and this is the only warning that they are being asked to.
        Log::warning('Member left without a current team after their team was deleted', [
            'user_id' => $member->id,
            'from_team_id' => $team->id,
        ]);

        $member->update(['current_team_id' => null]);
    }
}
