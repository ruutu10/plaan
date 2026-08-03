export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/** Abilities the signed-in user holds outside their teams (Spatie permissions). */
export type AuthAbilities = {
    viewAllTechnicalPlans: boolean;
    editAllTechnicalPlans: boolean;
    manageAllTeams: boolean;
    manageAllPerformances: boolean;
};

export type Auth = {
    user: User;
    can: AuthAbilities;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
