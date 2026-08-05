<?php

namespace App\Listeners;

use App\Actions\FindOrCreateUserByEmail;
use App\Actions\MagicLink\LogInAndVerifyEmail;
use App\Enums\SignupSource;
use App\Events\TeamInvited;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use MagicLink\MagicLink;

/**
 * Mail an invitation out with a link that signs its reader straight in as the
 * invited address — provisioning a lightweight account first if that address
 * has no account yet, exactly as {@see FindOrCreateUserByEmail} already does
 * for the self-service magic-link login. The link lands on the dashboard,
 * where the pending invitation waits to be accepted or declined; nothing here
 * grants team membership by itself.
 */
class SendTeamInvitation
{
    /**
     * How long the link stays good for: as long as the invitation itself
     * does, so a link that still works is never for an invitation that has
     * quietly expired underneath it.
     */
    private const LIFETIME_MINUTES = 60 * 24 * 3;

    /**
     * How many times the link may be followed — a small budget past one, for
     * a first visit that gets abandoned partway through.
     */
    private const MAX_VISITS = 5;

    public function __construct(private FindOrCreateUserByEmail $findOrCreateUser)
    {
        //
    }

    public function handle(TeamInvited $event): void
    {
        $invitation = $event->invitation;

        $invitedUser = $this->findOrCreateUser->handle($invitation->email, SignupSource::TeamMember);

        $action = new LogInAndVerifyEmail($invitedUser, redirect()->route('dashboard'));

        $loginUrl = MagicLink::create($action, lifetime: self::LIFETIME_MINUTES, numMaxVisits: self::MAX_VISITS)->url;

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation, $loginUrl));

        Log::info('Team invitation e-mail sent', [
            'invitation_id' => $invitation->id,
            'team_id' => $invitation->team_id,
            'new_account' => $invitedUser->wasRecentlyCreated,
        ]);
    }
}
