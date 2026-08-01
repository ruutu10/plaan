<?php

namespace App\Policies;

use App\Models\Performance;
use App\Models\Show;
use App\Models\User;

/**
 * Who may touch a show's dated performances: a member of the group that owns the
 * show, a member of the group playing the performance itself, or a technician
 * holding {@see Performance::EDIT_ALL_PERMISSION}. That permission is a right of
 * its own — it neither implies nor is implied by being allowed to edit the shows
 * themselves.
 *
 * Adding a performance is the exception: that stays with the show's own group,
 * so a guest troupe may correct its slot on somebody else's evening without
 * being able to put more of its own on the bill.
 */
class PerformancePolicy
{
    /**
     * Determine whether the user can list the performances of the given show:
     * a member of the show's own group, or of a group with a performance of its
     * own on the bill.
     */
    public function viewAny(User $user, Show $show): bool
    {
        return Performance::manageableFor($user, $show)
            || $show->performances()->editableBy($user)->exists();
    }

    /**
     * Determine whether the user can add a performance to the given show.
     */
    public function create(User $user, Show $show): bool
    {
        return Performance::manageableFor($user, $show);
    }

    /**
     * Determine whether the user can update the performance.
     */
    public function update(User $user, Performance $performance): bool
    {
        return $performance->isEditableBy($user);
    }

    /**
     * Determine whether the user can delete the performance.
     */
    public function delete(User $user, Performance $performance): bool
    {
        return $performance->isEditableBy($user);
    }
}
