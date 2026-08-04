<?php

namespace App\Policies;

use App\Http\Requests\Formats\SaveFormatRequest;
use App\Models\Format;
use App\Models\User;

class FormatPolicy
{
    /**
     * Determine whether the user can open the list of formats. Everyone signed in
     * may — what the list holds is narrowed to the formats of the user's own
     * groups by {@see Format::editableBy()}.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can enter a new format. Everyone signed in may;
     * which group it ends up under is held to the ones they belong to by
     * {@see SaveFormatRequest}.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the format. Wider than editing it: a
     * group with a performance of its own on somebody else's evening reaches
     * that evening's page to correct its own slot, and finds the format's own
     * details read-only there — see {@see Format::visibleTo()}.
     */
    public function view(User $user, Format $format): bool
    {
        return $format->isVisibleTo($user);
    }

    /**
     * Determine whether the user can update the format.
     */
    public function update(User $user, Format $format): bool
    {
        return $format->isEditableBy($user);
    }

    /**
     * Determine whether the user can delete the format. Deleting only puts the
     * format aside, so it is held to the same right as correcting one.
     */
    public function delete(User $user, Format $format): bool
    {
        return $format->isEditableBy($user);
    }
}
