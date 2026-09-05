import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    clearDraft,
    keepsLocalDraft,
    outgrowsLocalDraft,
    readDraft,
    writeDraft,
} from './draftStorage';
import { blankPlan } from './plan';

/** A stand-in for the browser's store — the unit tests run without a DOM. */
function fakeStorage(): Storage {
    const entries = new Map<string, string>();

    return {
        get length() {
            return entries.size;
        },
        key: (index: number) => [...entries.keys()][index] ?? null,
        getItem: (key: string) => entries.get(key) ?? null,
        setItem: (key: string, value: string) => void entries.set(key, value),
        removeItem: (key: string) => void entries.delete(key),
        clear: () => entries.clear(),
    };
}

beforeEach(() => {
    vi.stubGlobal('localStorage', fakeStorage());
});

describe('writeDraft', () => {
    it('stores the wizard state of a plan being written in this browser', () => {
        const plan = {
            ...blankPlan(),
            meta: { ...blankPlan().meta, performer: 'Mari' },
        };

        writeDraft({ step: 3, plan }, null);

        expect(readDraft()?.step).toBe(3);
        expect(readDraft()?.plan?.meta?.performer).toBe('Mari');
    });

    it('stores nothing while a plan opened by its share link is on screen', () => {
        const shared = { ...blankPlan(), token: 'shared-token' };

        writeDraft({ step: 6, plan: shared }, shared);

        expect(readDraft()).toBeNull();
    });

    it('leaves this browser own draft untouched while a shared plan is open', () => {
        const mine = {
            ...blankPlan(),
            meta: { ...blankPlan().meta, performer: 'Mari' },
        };
        writeDraft({ step: 2, plan: mine }, null);

        const shared = { ...blankPlan(), token: 'shared-token' };
        writeDraft({ step: 6, plan: shared }, shared);

        expect(readDraft()?.step).toBe(2);
        expect(readDraft()?.plan?.meta?.performer).toBe('Mari');
    });

    it('survives a store that refuses to take any more', () => {
        vi.stubGlobal('localStorage', {
            ...fakeStorage(),
            setItem: () => {
                throw new Error('QuotaExceededError');
            },
        });

        expect(() =>
            writeDraft({ step: 1, plan: blankPlan() }, null),
        ).not.toThrow();
    });
});

describe('readDraft', () => {
    it('has nothing to offer a browser that has never written a plan', () => {
        expect(readDraft()).toBeNull();
    });

    it('ignores a draft that is no longer readable', () => {
        localStorage.setItem('r10-techplan-v1', '{not json');

        expect(readDraft()).toBeNull();
    });
});

describe('readDraft upgrading a draft from before the cue list', () => {
    /**
     * Write a draft in the shape the wizard stored before a scene could hold
     * several cues, bypassing `writeDraft` — the point is to put the old shape
     * there, which the current types no longer describe.
     */
    function storeLegacyDraft(scene: Record<string, unknown>): void {
        localStorage.setItem(
            'r10-techplan-v1',
            JSON.stringify({
                step: 3,
                plan: {
                    ...blankPlan(),
                    scenes: [{ ...blankPlan().scenes[0], ...scene }],
                },
            }),
        );
    }

    const file = { id: 'media-1', name: 'avamuusika.mp3', size: 120 };

    it("carries an uploaded file over as the scene's first cue", () => {
        storeLegacyDraft({ sounds: undefined, soundUrl: '', soundFile: file });

        expect(readDraft()?.plan?.scenes?.[0].sounds).toEqual([
            { id: 'heli-1', url: '', file },
        ]);
    });

    it("carries a link over as the scene's first cue", () => {
        storeLegacyDraft({
            sounds: undefined,
            soundUrl: 'https://example.com/lugu.mp3',
            soundFile: null,
        });

        expect(readDraft()?.plan?.scenes?.[0].sounds).toEqual([
            { id: 'heli-1', url: 'https://example.com/lugu.mp3', file: null },
        ]);
    });

    it('leaves a scene that had no sound with no cues', () => {
        storeLegacyDraft({
            sounds: undefined,
            soundUrl: null,
            soundFile: null,
        });

        expect(readDraft()?.plan?.scenes?.[0].sounds).toEqual([]);
    });

    it('drops the fields the cue list replaced', () => {
        storeLegacyDraft({
            sounds: undefined,
            soundUrl: '',
            soundFile: file,
            soundUpload: true,
        });

        const scene = readDraft()?.plan?.scenes?.[0] as unknown as Record<
            string,
            unknown
        >;

        expect(scene).not.toHaveProperty('soundUrl');
        expect(scene).not.toHaveProperty('soundFile');
        expect(scene).not.toHaveProperty('soundUpload');
    });

    it('leaves a draft already written in the current shape alone', () => {
        const sounds = [{ id: 'heli-2', url: '', file }];

        writeDraft(
            {
                step: 3,
                plan: {
                    ...blankPlan(),
                    scenes: [{ ...blankPlan().scenes[0], sounds }],
                },
            },
            null,
        );

        expect(readDraft()?.plan?.scenes?.[0].sounds).toEqual(sounds);
    });
});

describe('clearDraft', () => {
    it('forgets the stored draft, as starting over does', () => {
        writeDraft({ step: 4, plan: blankPlan() }, null);

        clearDraft();

        expect(readDraft()).toBeNull();
    });
});

describe('outgrowsLocalDraft', () => {
    it('keeps a plan still only written in this browser as the local draft', () => {
        expect(outgrowsLocalDraft(blankPlan())).toBe(false);
    });

    it('outgrows the local draft once the plan has been saved and carries a token', () => {
        expect(outgrowsLocalDraft({ ...blankPlan(), token: 'abc123' })).toBe(
            true,
        );
    });
});

describe('keepsLocalDraft', () => {
    it('keeps a draft of a plan started in this browser', () => {
        expect(keepsLocalDraft(null)).toBe(true);
    });

    it('keeps no draft of a plan the wizard was opened on', () => {
        expect(keepsLocalDraft(blankPlan())).toBe(false);
    });
});
