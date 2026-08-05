<?php

namespace App\Listeners;

use App\Actions\MagicLink\LogInAndVerifyEmail;
use App\Events\TeamMemberProvisioned;
use App\Notifications\Teams\AddedToTeam;
use Illuminate\Support\Facades\Log;
use MagicLink\MagicLink;

/**
 * Welcome an account that only exists because it was added to a team: it has
 * no password anybody knows and no verified address, so the mail it gets is
 * the same link a magic-link login would send — one press logs it in and
 * settles the address in the same step, exactly as {@see LogInAndVerifyEmail}
 * already does for the login flow.
 */
class SendTeamMembershipWelcome
{
    /**
     * How long the link stays good for. An invitation this account never
     * asked for is not something to chase, so it gets the same three days a
     * team invitation itself would.
     */
    private const LIFETIME_MINUTES = 60 * 24 * 3;

    /**
     * How many times the link may be followed — a small budget past one, for
     * a first visit that gets abandoned partway through.
     */
    private const MAX_VISITS = 5;

    public function handle(TeamMemberProvisioned $event): void
    {
        $action = new LogInAndVerifyEmail(
            $event->member,
            redirect()->route('dashboard'),
            switchToTeamId: $event->team->id,
        );

        $url = MagicLink::create($action, lifetime: self::LIFETIME_MINUTES, numMaxVisits: self::MAX_VISITS)->url;

        $event->member->notify(new AddedToTeam($event->team, $url));

        Log::info('Sent a team membership welcome e-mail to a newly provisioned account', [
            'team_id' => $event->team->id,
            'member_id' => $event->member->id,
        ]);
    }
}
