/** One role as the account screens grant and show it. */
export interface ManagedRole {
    /** The slug the permission tables are written with, e.g. `technician`. */
    name: string;
    /** What the role is called in the interface. */
    label: string;
}

/** One account as the management screens list and edit it. */
export interface ManagedUser {
    id: number;
    name: string;
    email: string;
    /** An unproven address is one the house's own domains are not trusted on. */
    emailVerified: boolean;
    /** Which door the account came through; every account has one. */
    signupSource: string;
    signupSourceLabel: string;
    createdAt: string | null;
    /** How many groups the account stands in. */
    teamCount: number | null;
    roles: ManagedRole[];
}

/**
 * What the reader may write on an account beyond reaching it at all, which the
 * permission behind the screen has already settled.
 */
export interface ManagedUserPermissions {
    /** False on the reader's own account: nobody edits their own roles. */
    canUpdateRoles: boolean;
}

/** The fields an account is written through, and what the server refused. */
export interface ManagedUserFormData {
    name: string;
    email: string;
}

export type ManagedUserFieldErrors = Partial<
    Record<keyof ManagedUserFormData, string>
>;
