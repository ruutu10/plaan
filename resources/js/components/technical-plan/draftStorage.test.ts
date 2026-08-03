import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    clearDraft,
    keepsLocalDraft,
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

describe('clearDraft', () => {
    it('forgets the stored draft, as starting over does', () => {
        writeDraft({ step: 4, plan: blankPlan() }, null);

        clearDraft();

        expect(readDraft()).toBeNull();
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
