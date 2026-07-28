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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
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

        $team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::from($request->validated('role')),
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

        $membership->update(['role' => TeamRole::from($request->validated('role'))]);

        return TeamMemberResource::make($this->reread($team, $user));
    }

    /**
     * Take a member out of the team, moving them home if it was the team they
     * were working in.
     */
    public function destroy(Team $team, User $user): Response
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user) ?? false, SymfonyResponse::HTTP_FORBIDDEN, __('Tiimi omanikku ei saa eemaldada.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $home = $user->personalTeam() ?? $user->fallbackTeam($team);

            $home
                ? $user->switchTeam($home)
                : $user->update(['current_team_id' => null]);
        }

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
