<?php

namespace App\Events;

use App\Models\TeamInvitation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An invitation was written to a team, and somebody at the far end of an
 * e-mail address needs to hear about it. Fired so that building the mail —
 * and the magic link it carries — is a listener's concern rather than the
 * controller's.
 */
class TeamInvited
{
    use Dispatchable;

    public function __construct(public TeamInvitation $invitation)
    {
        //
    }
}
