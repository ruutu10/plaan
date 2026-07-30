<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }

    /**
     * Record a completed sign-in. Every way into the app ends in one of these
     * responses, so this is the one place that sees them all — and the only
     * thing that distinguishes a normal week from a credential-stuffing run.
     */
    protected function logSignIn(Request $request, string $method): void
    {
        Log::info('User signed in', [
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'method' => $method,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    protected function currentTeam(Request $request): Team
    {
        $user = $request->user();

        if (! $user) {
            Log::warning('Sign-in response reached without an authenticated user', [
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        $team = $user->currentTeam ?? $user->personalTeam();

        if (! $team) {
            // Every screen is scoped to a team, so a user without one cannot
            // get in at all — they are locked out until somebody notices.
            Log::error('User signed in but belongs to no team', [
                'user_id' => $user->id,
            ]);

            abort(403);
        }

        return $team;
    }
}
