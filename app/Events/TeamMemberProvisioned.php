<?php

namespace App\Events;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lightweight account was created for an address that had none, purely
 * because it was being added straight to a team. Fired so that welcoming the
 * new account — and asking it to verify itself — is a listener's concern
 * rather than the controller's.
 */
class TeamMemberProvisioned
{
    use Dispatchable;

    public function __construct(
        public Team $team,
        public User $member,
    ) {
        //
    }
}
