import type { Plan, PlanFile, Scene } from '@/types/technicalPlan';

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

        return raw ? upgradeDraft(JSON.parse(raw) as StoredDraft) : null;
    } catch {
        /* ignore malformed drafts */
        return null;
    }
}

/**
 * Bring a draft written before a scene could hold several cues up to the
 * current shape, turning its single link-or-file into the first entry of the
 * scene's list. The same rule the server's migration applies to stored plans.
 *
 * Done here rather than by starting the key over at `v2`, because the draft in
 * a browser is work nobody else has a copy of: an unsaved plan losing its cue
 * on a deploy is exactly the surprise this file exists to prevent.
 */
function upgradeDraft(draft: StoredDraft): StoredDraft {
    const scenes = draft.plan?.scenes;

    if (!Array.isArray(scenes)) {
        return draft;
    }

    return {
        ...draft,
        plan: {
            ...draft.plan,
            scenes: scenes.map((scene) => {
                if (Array.isArray(scene?.sounds)) {
                    return scene;
                }

                const legacy = scene as LegacyScene;
                const url = (legacy.soundUrl ?? '').trim();
                const file = legacy.soundFile ?? null;

                const rest = { ...legacy };
                delete rest.soundUrl;
                delete rest.soundFile;
                delete rest.soundUpload;

                return {
                    ...(rest as Scene),
                    sounds: file
                        ? [{ id: 'heli-1', url: '', file }]
                        : url !== ''
                          ? [{ id: 'heli-1', url, file: null }]
                          : [],
                };
            }),
        },
    };
}

/** A scene as drafts written before the cue list stored it. */
type LegacyScene = Scene & {
    soundUrl?: string;
    soundFile?: PlanFile | null;
    soundUpload?: boolean;
};

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
