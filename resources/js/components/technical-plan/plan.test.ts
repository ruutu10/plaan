import { describe, expect, it } from 'vitest';
import type { PlanSound, WizardConfig } from '@/types/technicalPlan';
import { blankIntermission, intermissionMinutes } from './intermissions';
import {
    blankPlan,
    blankScene,
    collapseScenes,
    hasSoundErrors,
    hydratePlan,
    isSmokeAllowedAt,
    nextSequentialId,
    soundErrors,
} from './plan';

/** The server's own limits, as the wizard is handed them. */
const config = {
    deadlineHours: 24,
    techEmail: 'tehnika@example.com',
    allowedExtensions: ['mp3', 'pdf'],
    soundExtensions: ['mp3', 'wav', 'ogg'],
    maxFileSize: 20971520,
    maxSoundsPerScene: 10,
    maxSoundUrlLength: 2000,
    maxIntermissionMinutes: 60,
    maxActMinutes: 240,
    smokeNotPossible: ['improkeskus'],
} satisfies WizardConfig;

describe('hydratePlan', () => {
    it('keeps the status and submission time a saved plan arrives with', () => {
        const hydrated = hydratePlan({
            token: 'abc123',
            status: 'submitted',
            submittedAt: '2026-07-20T18:00:00+00:00',
        });

        expect(hydrated.status).toBe('submitted');
        expect(hydrated.submittedAt).toBe('2026-07-20T18:00:00+00:00');
    });

    it.each(['draft', 'submitted', 'received', 'archived'])(
        'carries the %s status through untouched',
        (status) => {
            expect(hydratePlan({ token: 'abc123', status }).status).toBe(
                status,
            );
        },
    );

    it('treats a plan that has never reached the server as a draft', () => {
        expect(hydratePlan(null).status).toBe('draft');
        expect(hydratePlan(null).submittedAt).toBeNull();
    });

    it('falls back to the blank plan status when the payload omits one', () => {
        const hydrated = hydratePlan({ token: 'abc123' });

        expect(hydrated.status).toBe(blankPlan().status);
        expect(hydrated.submittedAt).toBeNull();
    });

    it('keeps the author a saved plan names, so the document can show them', () => {
        const hydrated = hydratePlan({
            token: 'abc123',
            authorEmail: 'esineja@naide.ee',
        });

        expect(hydrated.authorEmail).toBe('esineja@naide.ee');
    });

    it('keeps an interval a saved plan holds between its scenes', () => {
        const hydrated = hydratePlan({
            scenes: [
                blankScene('stseen-1'),
                blankIntermission('vaheaeg-1', 15),
                blankScene('stseen-2'),
            ],
        });

        expect(hydrated.scenes.map(intermissionMinutes)).toEqual([0, 15, 0]);
    });

    it('leaves a plan nobody has handed in yet without an author', () => {
        expect(hydratePlan(null).authorEmail).toBeNull();
        expect(hydratePlan({ token: 'abc123' }).authorEmail).toBeNull();
    });
});

describe('soundErrors', () => {
    function sound(overrides: Partial<PlanSound> = {}): PlanSound {
        return { ...blankPlan().sound, ...overrides };
    }

    it('asks for the detail behind a "jah" about microphones', () => {
        const errors = soundErrors(sound({ micsMode: 'yes' }));

        expect(errors.micsDetail).not.toBe('');
        expect(errors.musicianDetail).toBe('');
        expect(hasSoundErrors(sound({ micsMode: 'yes' }))).toBe(true);
    });

    it('asks for the detail behind a "jah" about a musician', () => {
        const errors = soundErrors(sound({ musicianMode: 'yes' }));

        expect(errors.musicianDetail).not.toBe('');
        expect(errors.micsDetail).toBe('');
    });

    it('takes whitespace for no answer at all', () => {
        expect(
            soundErrors(sound({ micsMode: 'yes', micsDetail: '   ' }))
                .micsDetail,
        ).not.toBe('');
    });

    it('is satisfied once the detail is written', () => {
        expect(
            hasSoundErrors(
                sound({
                    micsMode: 'yes',
                    micsDetail: '2 käsimikrofoni',
                    musicianMode: 'yes',
                    musicianDetail: 'Kitarr, ühendada helisüsteemi',
                }),
            ),
        ).toBe(false);
    });

    it('asks nothing of a question answered "ei", however empty', () => {
        expect(hasSoundErrors(blankPlan().sound)).toBe(false);
    });
});

describe('isSmokeAllowedAt', () => {
    it('rules smoke out at a hall the house has named', () => {
        expect(isSmokeAllowedAt('improkeskus', config)).toBe(false);
        expect(isSmokeAllowedAt('Improkeskus', config)).toBe(false);
        expect(isSmokeAllowedAt('  improkeskus  ', config)).toBe(false);
    });

    it('reads the venue out of the free text the board writes', () => {
        expect(isSmokeAllowedAt('Tartu improkeskus', config)).toBe(false);
        expect(isSmokeAllowedAt('improkeskuse BB, PR1, PR2', config)).toBe(
            false,
        );
    });

    it('allows smoke anywhere the house has not named', () => {
        expect(isSmokeAllowedAt('IMPEERIUM teatrisaal', config)).toBe(true);
        expect(isSmokeAllowedAt('Von Krahli teater', config)).toBe(true);
    });

    it('asks about smoke when no venue is named, rather than guessing', () => {
        expect(isSmokeAllowedAt('', config)).toBe(true);
        expect(isSmokeAllowedAt('   ', config)).toBe(true);
    });

    it('allows smoke everywhere when the house has named no hall', () => {
        expect(isSmokeAllowedAt('improkeskus', { smokeNotPossible: [] })).toBe(
            true,
        );
    });
});

describe('nextSequentialId', () => {
    it('starts at one when nothing is numbered yet', () => {
        expect(nextSequentialId([], 'heli-')).toBe('heli-1');
        expect(nextSequentialId([], 'stseen-')).toBe('stseen-1');
    });

    it('counts past the highest number rather than the length', () => {
        // The middle row was deleted; reusing "heli-2" would collide with a key
        // Vue is still holding for the row that is left.
        expect(nextSequentialId(['heli-1', 'heli-3'], 'heli-')).toBe('heli-4');
    });

    it('ignores ids that do not follow the prefix', () => {
        expect(nextSequentialId(['muu', 'heli-2x', 'heli-2'], 'heli-')).toBe(
            'heli-3',
        );
    });
});

describe('collapseScenes', () => {
    it('closes every card, whatever each was left at', () => {
        const scenes = [
            { ...blankScene('stseen-1'), collapsed: false },
            { ...blankScene('stseen-2'), collapsed: true },
            { ...blankScene('stseen-3') },
        ];

        collapseScenes(scenes);

        expect(scenes.map((scene) => scene.collapsed)).toEqual([
            true,
            true,
            true,
        ]);
    });

    it('has nothing to do to an empty list', () => {
        expect(() => collapseScenes([])).not.toThrow();
    });
});
