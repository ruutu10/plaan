<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\AddTeamMemberRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMember as TeamMemberResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The JSON API behind who belongs to a team. Every route is nested under the
 * team, so a membership is only ever reached through the team it is in.
 *
 * The owner is the one member these routes will not touch: demoting or
 * removing them would leave the team with nobody who fully answers for it.
 */
class TeamAdminMemberController extends Controller
{
    /**
     * Put an existing account straight into the team. This is the management
     * screen's shortcut past the invitation flow, which is why the account has
     * to exist already — see {@see AddTeamMemberRequest}.
     */
    public function store(AddTeamMemberRequest $request, Team $team): JsonResponse
    {
        /** @var User $member */
        $member = $request->member();

        $role = TeamRole::from($request->validated('role'));

        $team->memberships()->create([
            'user_id' => $member->id,
            'role' => $role,
        ]);

        // This is the shortcut past the invitation flow — nobody accepted
        // anything, so the log is the only record the member was put here.
        Log::notice('Member added to a team without an invitation', [
            'team_id' => $team->id,
            'member_id' => $member->id,
            'role' => $role->value,
            'added_by' => $request->user()->id,
        ]);

        return TeamMemberResource::make($this->reread($team, $member))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Change what a member is in the team.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): TeamMemberResource
    {
        Gate::authorize('updateMember', $team);

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        abort_if($membership->role === TeamRole::Owner, SymfonyResponse::HTTP_FORBIDDEN, __('Tiimi omaniku rolli ei saa muuta.'));

        $previousRole = $membership->role;
        $newRole = TeamRole::from($request->validated('role'));

        $membership->update(['role' => $newRole]);

        Log::notice('Team member role changed from the management screen', [
            'team_id' => $team->id,
            'member_id' => $user->id,
            'from_role' => $previousRole->value,
            'to_role' => $newRole->value,
            'changed_by' => $request->user()->id,
        ]);

        return TeamMemberResource::make($this->reread($team, $user));
    }

    /**
     * Take a member out of the team, moving them home if it was the team they
     * were working in.
     */
    public function destroy(Request $request, Team $team, User $user): Response
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user) ?? false, SymfonyResponse::HTTP_FORBIDDEN, __('Tiimi omanikku ei saa eemaldada.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->delete();

        $home = null;

        if ($user->isCurrentTeam($team)) {
            $home = $user->personalTeam() ?? $user->fallbackTeam($team);

            $home
                ? $user->switchTeam($home)
                : $user->update(['current_team_id' => null]);

            if (! $home) {
                Log::warning('Removed member has no team left to work in', [
                    'member_id' => $user->id,
                    'team_id' => $team->id,
                ]);
            }
        }

        Log::notice('Team member removed from the management screen', [
            'team_id' => $team->id,
            'member_id' => $user->id,
            'removed_by' => $request->user()->id,
            'moved_to_team_id' => $home?->id,
        ]);

        return response()->noContent();
    }

    /**
     * Read the member back through the team, so the answer carries the pivot
     * row {@see TeamMemberResource} reports the role from.
     */
    private function reread(Team $team, User $member): User
    {
        return $team->members()->whereKey($member->id)->firstOrFail();
    }
}
