import { describe, expect, it } from 'vitest';
import type {
    Plan,
    PlanFile,
    SceneSound,
    WizardConfig,
} from '@/types/technicalPlan';
import { isReady } from './files';
import { blankPlan } from './plan';
import {
    soundAudioUrl,
    soundFileStillUsed,
    soundHasSource,
    soundLinkError,
} from './sceneSounds';

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

/** One cue, defaulting to a stored file the way the server hands one back. */
function cue(overrides: Partial<SceneSound> = {}): SceneSound {
    return {
        id: 'heli-1',
        url: '',
        file: { id: 'media-1', name: 'lugu.mp3', size: 120 },
        ...overrides,
    };
}

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
