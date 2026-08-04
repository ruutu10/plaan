<?php

namespace App\Concerns;

use App\Models\User;

/**
 * For models that belong to a team and are reached through one. Each decides
 * for itself how it reaches its team — a format holds the id, a performance goes
 * through its format — so the query scope stays with the model. What is shared is
 * asking the question about a single record, which has to be answered by the
 * same scope the listings use or the two would eventually disagree.
 *
 * Expects the model to define an `editableBy` scope and an
 * `EDIT_ALL_PERMISSION` constant naming the house-wide permission that sees
 * past team boundaries.
 */
trait ScopedByTeamAccess
{
    /**
     * Whether the user may edit this record.
     */
    public function isEditableBy(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->editableBy($user)
            ->exists();
    }

    /**
     * Whether the user sees past team boundaries for this kind of record —
     * the technical crew, who work across the whole house.
     */
    protected static function seesEverything(User $user): bool
    {
        return $user->can(static::EDIT_ALL_PERMISSION);
    }
}
