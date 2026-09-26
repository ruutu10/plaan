<?php

namespace App\Listeners;

use App\Actions\BuildWelcomeLoginLink;
use App\Events\TeamMemberProvisioned;
use App\Notifications\Teams\AddedToTeam;
use Illuminate\Support\Facades\Log;

/**
 * Welcome an account that only exists because it was added to a team, with a
 * link that logs it in, settles its address and lands it in that team — see
 * {@see BuildWelcomeLoginLink}.
 */
class SendTeamMembershipWelcome
{
    public function __construct(private BuildWelcomeLoginLink $buildWelcomeLoginLink) {}

    public function handle(TeamMemberProvisioned $event): void
    {
        $url = $this->buildWelcomeLoginLink->handle($event->member, switchTo: $event->team);

        $event->member->notify(new AddedToTeam($event->team, $url, $event->addedBy));

        Log::info('Sent a team membership welcome e-mail to a newly provisioned account', [
            'team_id' => $event->team->id,
            'member_id' => $event->member->id,
        ]);
    }
}
