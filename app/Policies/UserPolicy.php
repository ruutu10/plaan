<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may keep the house's accounts straight. Unlike a team — where the answer
 * turns on the role the user holds inside it — an account has no owner to ask,
 * so reaching the screens at all is the flat house-wide
 * {@see User::MANAGE_PERMISSION}, guarded with `can:` on the routes. There is
 * no per-account shading to it worth a policy method of its own.
 *
 * What is left is the one question the permission does not answer by itself:
 * whose roles a holder of it may write. That is asked of every write, and asks
 * for the permission again rather than trusting the guard it sits behind —
 * handing out rights is the last thing to take on trust.
 */
class UserPolicy
{
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
