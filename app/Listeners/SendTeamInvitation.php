<?php

namespace App\Listeners;

use App\Actions\BuildWelcomeLoginLink;
use App\Actions\FindOrCreateUserByEmail;
use App\Enums\SignupSource;
use App\Events\TeamInvited;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Mail an invitation out with a link that signs its reader straight in as the
 * invited address — provisioning a lightweight account first if that address
 * has no account yet, exactly as {@see FindOrCreateUserByEmail} already does
 * for the self-service magic-link login. The link lands on the dashboard,
 * where the pending invitation waits to be accepted or declined; nothing here
 * grants team membership by itself. The link lives as long as the invitation
 * does; see {@see BuildWelcomeLoginLink}.
 */
class SendTeamInvitation
{
    public function __construct(
        private FindOrCreateUserByEmail $findOrCreateUser,
        private BuildWelcomeLoginLink $buildWelcomeLoginLink,
    ) {
        //
    }

    public function handle(TeamInvited $event): void
    {
        $invitation = $event->invitation;

        $invitedUser = $this->findOrCreateUser->handle($invitation->email, SignupSource::TeamMember);

        $loginUrl = $this->buildWelcomeLoginLink->handle($invitedUser);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation, $loginUrl));

        Log::info('Team invitation e-mail sent', [
            'invitation_id' => $invitation->id,
            'team_id' => $invitation->team_id,
            'new_account' => $invitedUser->wasRecentlyCreated,
        ]);
    }
}
