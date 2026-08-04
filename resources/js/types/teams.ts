export type TeamRole = 'owner' | 'admin' | 'member';

export type Team = {
    id: number;
    name: string;
    slug: string;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole;
    role_label: string;
};

export type TeamInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamInvitationContext = {
    code: string;
    teamName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    team: {
        name: string;
        slug: string;
    };
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};

/** One team as the management screens list and edit it. */
export interface ManagedTeam {
    id: number;
    name: string;
    slug: string;
    /** How many people belong to the team; only the listing counts them. */
    memberCount: number | null;
    /** How much the team has staged; only the listing counts it. */
    formatCount: number | null;
    /** The people themselves, which only the edit page reads. */
    members?: ManagedTeamMember[];
}

/** One person in a team, as the management screen lists them. */
export interface ManagedTeamMember {
    id: number;
    name: string;
    email: string;
    role: TeamRole;
    roleLabel: string;
    /** The owner is the one member nobody may demote or take out. */
    isOwner: boolean;
}

/** What the reader may write on a team, as the management screen is told. */
export interface ManagedTeamPermissions {
    canUpdate: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
}

/** The fields a team is written through, and what the server refused. */
export interface ManagedTeamFormData {
    name: string;
}

export type ManagedTeamFieldErrors = Partial<
    Record<keyof ManagedTeamFormData, string>
>;

/** The fields a new member is added through. */
export interface AddTeamMemberFormData {
    email: string;
    role: TeamRole;
}

export type AddTeamMemberFieldErrors = Partial<
    Record<keyof AddTeamMemberFormData, string>
>;
