<?php

namespace App\Policies;

use App\Models\Performance;
use App\Models\User;

/**
 * Who may keep the house's accounts straight. Unlike a team — where the answer
 * turns on the role the user holds inside it — an account has no owner to ask,
 * so reaching the screens at all is the flat house-wide
 * {@see User::MANAGE_PERMISSION}, guarded with `can:` on the routes. There is
 * no per-account shading to it worth a policy method of its own.
 *
 * The exception is {@see search()}: finding somebody by name is needed well
 * beyond the account screens, and has a reach of its own.
 *
 * What is left is the one question the permission does not answer by itself:
 * whose roles a holder of it may write. That is asked of every write, and asks
 * for the permission again rather than trusting the guard it sits behind —
 * handing out rights is the last thing to take on trust.
 */
class UserPolicy
{
    /**
     * Determine whether the user can look accounts up by name — to put
     * somebody on a night's staff, say.
     *
     * Wider than managing accounts: anybody who looks after something people
     * can be added to may search — the crew, whoever runs the accounts, and
     * every member of a group. Narrower than having a login: somebody who has
     * only just signed themselves up belongs to nothing yet, and has nobody to
     * look for.
     */
    public function search(User $user): bool
    {
        return $user->can(User::MANAGE_PERMISSION)
            || $user->can(Performance::EDIT_ALL_PERMISSION)
            || $user->teams()->exists();
    }

    /**
     * Determine whether the user can grant or take away the subject's roles.
     *
     * Nobody edits their own. The permission that opens these screens is
     * carried by a role, so leaving somebody able to drop it leaves them able
     * to lock themselves out — with no screen left to open it again. Another
     * technician does it.
     */
    public function updateRoles(User $user, User $subject): bool
    {
        return $user->can(User::MANAGE_PERMISSION) && ! $user->is($subject);
    }
}
