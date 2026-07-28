<?php

namespace App\Policies;

use App\Models\Performance;
use App\Models\Show;
use App\Models\User;

/**
 * Who may touch a show's dated performances: a member of the group that owns the
 * show, or a technician holding {@see Performance::EDIT_ALL_PERMISSION}. That
 * permission is a right of its own — it neither implies nor is implied by
 * being allowed to edit the shows themselves.
 */
class PerformancePolicy
{
    /**
     * Determine whether the user can list the performances of the given show.
     */
    public function viewAny(User $user, Show $show): bool
    {
        return Performance::manageableFor($user, $show);
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
