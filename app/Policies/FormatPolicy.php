<?php

namespace App\Policies;

use App\Http\Requests\Shows\SaveShowRequest;
use App\Models\Show;
use App\Models\User;

class ShowPolicy
{
    /**
     * Determine whether the user can open the list of shows. Everyone signed in
     * may — what the list holds is narrowed to the shows of the user's own
     * groups by {@see Show::editableBy()}.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can enter a new show. Everyone signed in may;
     * which group it ends up under is held to the ones they belong to by
     * {@see SaveShowRequest}.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the show. Wider than editing it: a
     * group with a performance of its own on somebody else's evening reaches
     * that evening's page to correct its own slot, and finds the show's own
     * details read-only there — see {@see Show::visibleTo()}.
     */
    public function view(User $user, Show $show): bool
    {
        return $show->isVisibleTo($user);
    }

    /**
     * Determine whether the user can update the show.
     */
    public function update(User $user, Show $show): bool
    {
        return $show->isEditableBy($user);
    }

    /**
     * Determine whether the user can delete the show. Deleting only puts the
     * show aside, so it is held to the same right as correcting one.
     */
    public function delete(User $user, Show $show): bool
    {
        return $show->isEditableBy($user);
    }
}
