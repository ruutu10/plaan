<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;

/**
 * Who may touch a team. Ordinarily the answer comes from the role the user
 * holds inside that team, but a technician holding
 * {@see Team::EDIT_ALL_PERMISSION} keeps every group in the house straight and
 * is answered yes wherever a team's own admin would be.
 */
class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $team->isEditableBy($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $this->hasTeamPermission($user, $team, TeamPermission::UpdateTeam);
    }

    /**
     * Determine whether the user can leave the team.
     */
    public function leave(User $user, Team $team): bool
    {
        return ! $team->is_personal
            && $user->belongsToTeam($team)
            && ! $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add a member to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $this->hasTeamPermission($user, $team, TeamPermission::AddMember);
    }

    /**
     * Determine whether the user can update a member's role in the team.
     */
    public function updateMember(User $user, Team $team): bool
    {
        return $this->hasTeamPermission($user, $team, TeamPermission::UpdateMember);
    }

    /**
     * Determine whether the user can remove a member from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $this->hasTeamPermission($user, $team, TeamPermission::RemoveMember);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function inviteMember(User $user, Team $team): bool
    {
        return $this->hasTeamPermission($user, $team, TeamPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Team $team): bool
    {
        return $this->hasTeamPermission($user, $team, TeamPermission::CancelInvitation);
    }

    /**
     * Determine whether the user can delete the model. A personal team is the
     * one team nobody may delete: it is the home a user is put back into when
     * the groups they joined go away.
     */
    public function delete(User $user, Team $team): bool
    {
        return ! $team->is_personal && $this->hasTeamPermission($user, $team, TeamPermission::DeleteTeam);
    }

    /**
     * Determine whether the user holds the given right on the team, either
     * through their role in it or through the house-wide permission.
     */
    private function hasTeamPermission(User $user, Team $team, TeamPermission $permission): bool
    {
        return $user->hasTeamPermission($team, $permission)
            || $user->can(Team::EDIT_ALL_PERMISSION);
    }
}
