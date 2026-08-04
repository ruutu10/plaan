<?php

namespace App\Policies;

use App\Models\Format;
use App\Models\Performance;
use App\Models\User;

/**
 * Who may touch a format's dated performances: a member of the group that owns the
 * format, a member of the group playing the performance itself, or a technician
 * holding {@see Performance::EDIT_ALL_PERMISSION}. That permission is a right of
 * its own — it neither implies nor is implied by being allowed to edit the formats
 * themselves.
 *
 * Adding a performance is the exception: that stays with the format's own group,
 * so a guest troupe may correct its slot on somebody else's evening without
 * being able to put more of its own on the bill.
 */
class PerformancePolicy
{
    /**
     * Determine whether the user can list the performances of the given format:
     * a member of the format's own group, or of a group with a performance of its
     * own on the bill.
     */
    public function viewAny(User $user, Format $format): bool
    {
        return Performance::manageableFor($user, $format)
            || $format->performances()->editableBy($user)->exists();
    }

    /**
     * Determine whether the user can add a performance to the given format.
     */
    public function create(User $user, Format $format): bool
    {
        return Performance::manageableFor($user, $format);
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
