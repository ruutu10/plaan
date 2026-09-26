<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A technician created an account by hand from the management screen. Fired
 * so that welcoming the new account — and asking it to verify itself — is a
 * listener's concern rather than the controller's.
 */
class UserProvisioned
{
    use Dispatchable;

    /**
     * @param  User  $createdBy  the technician who created the account — the
     *                           welcome mail names them, so the newcomer can
     *                           see who is behind an account they never asked for
     * @param  bool  $sendWelcome  false when the technician chose to tell the
     *                             newcomer themselves; they can still ask for a
     *                             magic link from the login page
     */
    public function __construct(
        public User $user,
        public User $createdBy,
        public bool $sendWelcome = true,
    ) {
        //
    }
}
