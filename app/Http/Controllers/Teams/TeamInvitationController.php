<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Events\TeamInvited;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamInvitationRequest;
use App\Http\Requests\Teams\RespondToTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateTeamInvitationRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('inviteMember', $team);

        $invitation = $team->invitations()->create([
            'email' => $request->validated('email'),
            'role' => TeamRole::from($request->validated('role')),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(3),
        ]);

        TeamInvited::dispatch($invitation);

        // The invitation code identifies it through the whole flow; the address
        // it went to stays out of the log.
        Log::info('Team invitation sent', [
            'invitation_id' => $invitation->id,
            'team_id' => $team->id,
            'role' => $invitation->role->value,
            'invited_by' => $request->user()->id,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kutse saadetud.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Cancel the specified invitation.
     */
    public function destroy(Request $request, Team $team, TeamInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->team_id === $team->id, 404);

        Gate::authorize('cancelInvitation', $team);

        $invitation->delete();

        Log::info('Team invitation cancelled', [
            'invitation_id' => $invitation->id,
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Accept the invitation.
     */
    public function accept(RespondToTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $invitation) {
            $team = $invitation->team;

            $membership = $team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);

            $user->switchTeam($team);

            Log::info('Team invitation accepted', [
                'invitation_id' => $invitation->id,
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => $membership->role->value,
                // An invitation accepted by somebody already in the team grants
                // nothing; the role they keep is the one they had.
                'already_a_member' => ! $membership->wasRecentlyCreated,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('dashboard');
    }

    /**
     * Decline the invitation.
     */
    public function decline(RespondToTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Log::info('Team invitation declined', [
            'invitation_id' => $invitation->id,
            'team_id' => $invitation->team_id,
            'user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }
}
