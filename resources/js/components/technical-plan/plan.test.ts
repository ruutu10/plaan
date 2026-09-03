import { describe, expect, it } from 'vitest';
import type { PlanSound } from '@/types/technicalPlan';
import {
    blankPlan,
    hasSoundErrors,
    hydratePlan,
    MAX_SOUND_URL_LENGTH,
    soundErrors,
    soundLinkError,
} from './plan';

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

describe('soundLinkError', () => {
    it('accepts a plain http(s) address', () => {
        expect(soundLinkError('https://example.com/muusika.mp3')).toBeNull();
        expect(soundLinkError('http://example.com/muusika.mp3')).toBeNull();
    });

    it('accepts a link the performer padded with whitespace', () => {
        expect(soundLinkError('  https://example.com/lugu.mp3  ')).toBeNull();
    });

    it('refuses a link that is not there at all', () => {
        expect(soundLinkError('')).not.toBeNull();
        expect(soundLinkError('   ')).not.toBeNull();
    });

    it('refuses an address with no scheme, which no player could follow', () => {
        expect(soundLinkError('example.com/lugu.mp3')).not.toBeNull();
        expect(soundLinkError('/failid/lugu.mp3')).not.toBeNull();
        expect(soundLinkError('lihtsalt natuke teksti')).not.toBeNull();
    });

    it('refuses a scheme that is not the web — the link becomes an href', () => {
         
        expect(soundLinkError('javascript:alert(1)')).not.toBeNull();
        expect(soundLinkError('data:audio/mp3;base64,AAAA')).not.toBeNull();
        expect(soundLinkError('ftp://example.com/lugu.mp3')).not.toBeNull();
    });

    it('refuses a link longer than the server would store', () => {
        const tooLong = 'https://example.com/' + 'a'.repeat(MAX_SOUND_URL_LENGTH);

        expect(soundLinkError(tooLong)).not.toBeNull();
    });
});
