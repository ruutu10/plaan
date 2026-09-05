import { describe, expect, it } from 'vitest';
import type {
    Plan,
    PlanFile,
    PlanSound,
    SceneSound,
    WizardConfig,
} from '@/types/technicalPlan';
import {
    blankPlan,
    blankScene,
    canSaveDraft,
    collapseScenes,
    hasSoundErrors,
    hydratePlan,
    isDelivered,
    isDraft,
    isReady,
    nextSequentialId,
    soundAudioUrl,
    soundErrors,
    soundFileStillUsed,
    soundHasSource,
    soundLinkError,
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
} satisfies WizardConfig;

/** One cue, defaulting to a stored file the way the server hands one back. */
function cue(overrides: Partial<SceneSound> = {}): SceneSound {
    return {
        id: 'heli-1',
        url: '',
        file: { id: 'media-1', name: 'lugu.mp3', size: 120 },
        ...overrides,
    };
}

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
        expect(
            soundLinkError('https://example.com/muusika.mp3', config),
        ).toBeNull();
        expect(
            soundLinkError('http://example.com/muusika.mp3', config),
        ).toBeNull();
    });

    it('accepts a link the performer padded with whitespace', () => {
        expect(
            soundLinkError('  https://example.com/lugu.mp3  ', config),
        ).toBeNull();
    });

    it('refuses a link that is not there at all', () => {
        expect(soundLinkError('', config)).not.toBeNull();
        expect(soundLinkError('   ', config)).not.toBeNull();
    });

    it('refuses an address with no scheme, which no player could follow', () => {
        expect(soundLinkError('example.com/lugu.mp3', config)).not.toBeNull();
        expect(soundLinkError('/failid/lugu.mp3', config)).not.toBeNull();
        expect(soundLinkError('lihtsalt natuke teksti', config)).not.toBeNull();
    });

    it('refuses a scheme that is not the web — the link becomes an href', () => {
        expect(soundLinkError('javascript:alert(1)', config)).not.toBeNull();
        expect(
            soundLinkError('data:audio/mp3;base64,AAAA', config),
        ).not.toBeNull();
        expect(
            soundLinkError('ftp://example.com/lugu.mp3', config),
        ).not.toBeNull();
    });

    it('refuses a link longer than the server would store', () => {
        const tooLong =
            'https://example.com/' + 'a'.repeat(config.maxSoundUrlLength);

        expect(soundLinkError(tooLong, config)).not.toBeNull();
    });
});

describe('isDelivered', () => {
    it('counts the statuses the technical team is already holding', () => {
        expect(isDelivered('submitted')).toBe(true);
        expect(isDelivered('received')).toBe(true);
    });

    it('leaves out a plan the crew has not been handed', () => {
        // A blank plan is a draft, which is what makes the review step offer to
        // submit rather than to update.
        expect(isDelivered(blankPlan().status)).toBe(false);
        // Archived: its night has been played, so submitting again is a fresh
        // hand-in and the crew is told about it.
        expect(isDelivered('archived')).toBe(false);
    });
});

describe('isDraft', () => {
    it('counts a plan nobody has been handed', () => {
        expect(isDraft('draft')).toBe(true);
        expect(isDraft(blankPlan().status)).toBe(true);
    });

    it('leaves out every status a plan reaches after it is submitted', () => {
        expect(isDraft('submitted')).toBe(false);
        expect(isDraft('received')).toBe(false);
        expect(isDraft('archived')).toBe(false);
        expect(isDraft(null)).toBe(false);
    });
});

describe('canSaveDraft', () => {
    it('offers a draft save on a plan that has never been saved', () => {
        expect(canSaveDraft(blankPlan())).toBe(true);
    });

    it('offers one again on a plan saved and left as a draft', () => {
        expect(canSaveDraft({ token: 'R10-2026-abc', status: 'draft' })).toBe(
            true,
        );
    });

    it('stops offering one once the crew is holding the plan', () => {
        expect(
            canSaveDraft({ token: 'R10-2026-abc', status: 'submitted' }),
        ).toBe(false);
        expect(
            canSaveDraft({ token: 'R10-2026-abc', status: 'received' }),
        ).toBe(false);
    });

    it('stops offering one on a plan whose night has been played', () => {
        // Archived is not a draft to go back to: saving one could not put it
        // back to draft anyway — see App\Actions\SaveTechnicalPlan.
        expect(
            canSaveDraft({ token: 'R10-2026-abc', status: 'archived' }),
        ).toBe(false);
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

describe('soundHasSource', () => {
    it('counts a stored file', () => {
        expect(soundHasSource(cue())).toBe(true);
    });

    it('counts a file the server handed back without an upload status', () => {
        // The document fixtures carry files in exactly this shape, so the
        // permissive reading of `isReady` is load-bearing, not incidental.
        expect(isReady({ id: 'media-1', name: 'lugu.mp3', size: 1 })).toBe(
            true,
        );
    });

    it('does not count a file that is still going up, or failed', () => {
        expect(
            soundHasSource(
                cue({ file: { ...cue().file!, status: 'uploading' } }),
            ),
        ).toBe(false);
        expect(
            soundHasSource(cue({ file: { ...cue().file!, status: 'error' } })),
        ).toBe(false);
    });

    it('counts a link, but not one that is only whitespace', () => {
        expect(
            soundHasSource(
                cue({ url: 'https://example.com/a.mp3', file: null }),
            ),
        ).toBe(true);
        expect(soundHasSource(cue({ url: '   ', file: null }))).toBe(false);
        expect(soundHasSource(cue({ url: '', file: null }))).toBe(false);
    });
});

describe('soundFileStillUsed', () => {
    function planWith(...sceneSounds: SceneSound[][]): Plan {
        const plan = blankPlan();

        plan.scenes = sceneSounds.map((sounds, index) => ({
            ...plan.scenes[0],
            id: `stseen-${index + 1}`,
            sounds,
        }));

        return plan;
    }

    it('holds on to a file a second scene is still playing', () => {
        // Reusing one sting across two scenes: dropping it from one must not
        // delete it out from under the other.
        const plan = planWith(
            [],
            [cue({ file: { id: 'media-1', name: 'a.mp3', size: 1 } })],
        );

        expect(soundFileStillUsed(plan, 'media-1')).toBe(true);
    });

    it('lets go once the last scene naming it has', () => {
        expect(soundFileStillUsed(planWith([], []), 'media-1')).toBe(false);
    });

    it('does not confuse one file for another', () => {
        const plan = planWith([
            cue({ file: { id: 'media-2', name: 'b.mp3', size: 1 } }),
        ]);

        expect(soundFileStillUsed(plan, 'media-1')).toBe(false);
    });
});

describe('soundAudioUrl', () => {
    const stored = (name: string): PlanFile => ({
        id: 'media-1',
        name,
        size: 1,
        url: '/api/attachments/media-1',
        status: 'ready',
    });

    it('plays an upload off its streaming URL, judged by its stored name', () => {
        // The streaming URL carries no extension, so the name is what decides.
        expect(soundAudioUrl(cue({ file: stored('lugu.mp3') }))).toBe(
            '/api/attachments/media-1',
        );
    });

    it('will not play an upload the browser cannot decode', () => {
        expect(soundAudioUrl(cue({ file: stored('noodid.pdf') }))).toBeNull();
    });

    it('will not play an upload that has not finished', () => {
        expect(
            soundAudioUrl(
                cue({ file: { ...stored('lugu.mp3'), status: 'uploading' } }),
            ),
        ).toBeNull();
    });

    it('plays a link that points straight at a file', () => {
        expect(
            soundAudioUrl(
                cue({ url: 'https://example.com/lugu.mp3', file: null }),
            ),
        ).toBe('https://example.com/lugu.mp3');
    });

    it('leaves a sharing page to be opened rather than played', () => {
        // A YouTube page names no audio file, and a query string that mentions
        // one is not the resource being fetched.
        expect(
            soundAudioUrl(
                cue({ url: 'https://youtube.com/watch?v=abc', file: null }),
            ),
        ).toBeNull();
        expect(
            soundAudioUrl(
                cue({ url: 'https://example.com/d?file=lugu.mp3', file: null }),
            ),
        ).toBeNull();
    });
});
