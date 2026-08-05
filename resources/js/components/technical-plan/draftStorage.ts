import type { Plan } from '@/types/technicalPlan';

/**
 * Where the half-written plan of the browser in front of us lives. Versioned,
 * so a shape change can start over rather than hydrate nonsense.
 */
const STORAGE_KEY = 'r10-techplan-v1';

/** The wizard state as it is written down between visits. */
export type StoredDraft = {
    step?: number;
    plan?: Partial<Plan>;
};

/**
 * Whether the wizard on screen is one this browser should keep a draft of.
 * A plan opened by its share link belongs to the link, not to this browser:
 * writing it down would prefill the next new plan started here with somebody
 * else's content.
 */
export function keepsLocalDraft(openedPlan: Plan | null | undefined): boolean {
    return !openedPlan;
}

/** The draft left in this browser, if there is one still readable. */
export function readDraft(): StoredDraft | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        return raw ? (JSON.parse(raw) as StoredDraft) : null;
    } catch {
        /* ignore malformed drafts */
        return null;
    }
}

/**
 * Write the wizard's state down as this browser's draft — unless the wizard
 * was opened on a plan of its own, which {@see keepsLocalDraft} leaves alone.
 * The guard lives here rather than at the call site so that no future writer
 * can quietly store a shared plan.
 */
export function writeDraft(
    draft: StoredDraft,
    openedPlan: Plan | null | undefined,
): void {
    if (!keepsLocalDraft(openedPlan)) {
        return;
    }

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
    } catch {
        /* ignore quota errors */
    }
}

/**
 * Whether a plan that has just been saved should stop being kept as this
 * browser's local draft. Once a plan carries a token it has its own home on
 * the server — leaving it as the local draft would have the next brand-new
 * plan started in this browser resume at its step (typically the review step
 * it was saved from) instead of starting at the beginning.
 */
export function outgrowsLocalDraft(plan: Pick<Plan, 'token'>): boolean {
    return Boolean(plan.token);
}

/** Forget this browser's draft, as starting over does. */
export function clearDraft(): void {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        /* ignore */
    }
}
