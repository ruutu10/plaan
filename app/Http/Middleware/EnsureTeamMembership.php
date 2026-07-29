<?php

namespace App\Http\Middleware;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $team] = [$request->user(), $this->team($request)];

        if (! $user || ! $team || ! $user->belongsToTeam($team)) {
            // The gate every team-scoped route sits behind. A refusal here is
            // either a stale link or somebody trying team slugs by hand, and
            // the two are only told apart by how often it happens.
            Log::warning('Refused access to a team route', [
                'user_id' => $user?->id,
                'team_id' => $team?->id,
                'reason' => match (true) {
                    ! $user => 'unauthenticated',
                    ! $team => 'unknown_team',
                    default => 'not_a_member',
                },
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        $this->ensureTeamMemberHasRequiredRole($user, $team, $minimumRole);

        if ($request->route('current_team') && ! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        return $next($request);
    }

    /**
     * Ensure the given user has at least the given role, if applicable.
     */
    protected function ensureTeamMemberHasRequiredRole(User $user, Team $team, ?string $minimumRole): void
    {
        if ($minimumRole === null) {
            return;
        }

        $role = $user->teamRole($team);

        $requiredRole = TeamRole::tryFrom($minimumRole);

        if ($requiredRole === null) {
            // A route declared with a role the enum does not know is a wiring
            // mistake, and it locks everybody out rather than letting them in.
            Log::error('Team route requires a role that does not exist', [
                'required_role' => $minimumRole,
                'team_id' => $team->id,
            ]);

            abort(403);
        }

        if ($role === null || ! $role->isAtLeast($requiredRole)) {
            Log::warning('Refused a team route to a member below the required role', [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'role' => $role?->value,
                'required_role' => $requiredRole->value,
            ]);

            abort(403);
        }
    }

    /**
     * Get the team associated with the request.
     */
    protected function team(Request $request): ?Team
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if (is_string($team)) {
            $team = Team::where('slug', $team)->first();
        }

        return $team;
    }
}
