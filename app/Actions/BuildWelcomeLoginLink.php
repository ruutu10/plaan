<?php

namespace App\Actions;

use App\Actions\MagicLink\LogInAndVerifyEmail;
use App\Models\Team;
use App\Models\User;
use MagicLink\MagicLink;

/**
 * The link a welcome or invitation mail is made of, for an account somebody
 * else brought into being: it has no password anybody knows and no verified
 * address, so one press logs it in and settles the address in the same step,
 * exactly as {@see LogInAndVerifyEmail} already does for the login flow.
 */
class BuildWelcomeLoginLink
{
    /**
     * How long the link stays good for. Nobody asked for this mail, so it is
     * not something to chase — and a team invitation lives exactly as long, so
     * a link that still works is never for an invitation that has quietly
     * expired underneath it.
     */
    private const LIFETIME_MINUTES = 60 * 24 * 3;

    /**
     * How many times the link may be followed — a small budget past one, for
     * a first visit that gets abandoned partway through.
     */
    private const MAX_VISITS = 5;

    /**
     * Build a single-user login link that lands on the dashboard.
     *
     * @param  Team|null  $switchTo  a team to make current once logged in — for
     *                               a newcomer whose account only exists because
     *                               they were added to it
     */
    public function handle(User $user, ?Team $switchTo = null): string
    {
        $action = new LogInAndVerifyEmail(
            $user,
            redirect()->route('dashboard'),
            switchToTeamId: $switchTo?->id,
        );

        return MagicLink::create($action, lifetime: self::LIFETIME_MINUTES, numMaxVisits: self::MAX_VISITS)->url;
    }
}
