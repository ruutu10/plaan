<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $newRole = TeamRole::from($request->validated('role'));

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $previousRole = $membership->role;

        $membership->update(['role' => $newRole]);

        // What somebody may do in a team is the thing worth being able to
        // reconstruct after the fact, so both ends of the change are recorded.
        Log::notice('Team member role changed', [
            'team_id' => $team->id,
            'member_id' => $user->id,
            'from_role' => $previousRole->value,
            'to_role' => $newRole->value,
            'changed_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Request $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        $moved = $user->isCurrentTeam($team);

        if ($moved) {
            $user->switchTeam($user->personalTeam());
        }

        Log::notice('Team member removed', [
            'team_id' => $team->id,
            'member_id' => $user->id,
            'removed_by' => $request->user()->id,
            'moved_home' => $moved,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
