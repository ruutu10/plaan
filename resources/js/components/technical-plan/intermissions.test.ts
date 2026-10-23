import { describe, expect, it } from 'vitest';
import type { Scene, WizardConfig } from '@/types/technicalPlan';
import {
    actLabel,
    buildBlocks,
    blankIntermission,
    intermissionLabel,
    intermissionMinutes,
    isIntermission,
    readIntermissionForm,
    showParts,
} from './intermissions';
import { blankScene } from './plan';

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

describe('intermissionMinutes', () => {
    it('reads the length off an entry that is the interval', () => {
        expect(intermissionMinutes(blankIntermission('vaheaeg-1', 15))).toBe(
            15,
        );
        expect(isIntermission(blankIntermission('vaheaeg-1', 15))).toBe(true);
    });

    it('counts an ordinary scene as no interval at all', () => {
        expect(intermissionMinutes(blankScene())).toBe(0);
        expect(isIntermission(blankScene())).toBe(false);
    });

    it('reads a plan stored before intervals existed as all scenes', () => {
        // The key is simply absent on every scene of such a plan.
        expect(intermissionMinutes({} as Scene)).toBe(0);
    });

    it('takes a length that is not a real one for no interval', () => {
        expect(intermissionMinutes({ intermission: 0 })).toBe(0);
        expect(intermissionMinutes({ intermission: -15 })).toBe(0);
        expect(intermissionMinutes({ intermission: Number.NaN })).toBe(0);
    });

    it('names the interval the same way every reader shows it', () => {
        expect(intermissionLabel(15)).toBe('Vaheaeg — 15 min');
    });
});

describe('showParts', () => {
    /** A running order written as "3" scenes and "|15/40" intervals. */
    function order(...entries: (number | string)[]): Scene[] {
        return entries.flatMap((entry) => {
            if (typeof entry === 'number') {
                return Array.from({ length: entry }, (_, index) =>
                    blankScene(`stseen-${index + 1}`),
                );
            }

            const [interval, act] = entry.split('/');

            return [
                blankIntermission(
                    'vaheaeg-1',
                    Number(interval),
                    act === undefined ? null : Number(act),
                ),
            ];
        });
    }

    it('works the closing part out of what is left of the evening', () => {
        // 90 minutes, less the 15-minute break and the 40 minutes named.
        expect(showParts(order(2, '15/40', 3), 90)).toEqual([
            { num: 1, start: 0, minutes: 40 },
            { num: 2, start: 3, minutes: 35 },
        ]);
    });

    it('splits a show played in three parts the same way', () => {
        expect(showParts(order(1, '10/20', 1, '10/25', 1), 90)).toEqual([
            { num: 1, start: 0, minutes: 20 },
            { num: 2, start: 2, minutes: 25 },
            { num: 3, start: 4, minutes: 25 },
        ]);
    });

    it('leaves the closing part unknown when a part was not named', () => {
        expect(showParts(order(1, '15', 1), 90)).toEqual([
            { num: 1, start: 0, minutes: null },
            { num: 2, start: 2, minutes: null },
        ]);
    });

    it('leaves the closing part unknown on a night nobody has timed', () => {
        expect(showParts(order(1, '15/40', 1), null)[1].minutes).toBeNull();
    });

    /**
     * The parts filling the evening leaves the closing one nothing, which is a
     * number the performer has to put right — not one to invent.
     */
    it('reports no length rather than a negative one', () => {
        expect(showParts(order(1, '15/80', 1), 90)[1].minutes).toBeNull();
        expect(showParts(order(1, '15/75', 1), 90)[1].minutes).toBeNull();
    });

    it('has nothing to split on a show played straight through', () => {
        expect(showParts(order(3), 90)).toEqual([]);
    });

    it('has nothing to split when the interval stands outside the scenes', () => {
        // Where a freshly added interval sits until it is dragged into place.
        expect(showParts(order(3, '15/40'), 90)).toEqual([]);
        expect(showParts(order('15/40', 3), 90)).toEqual([]);
    });

    it('names each part the way every reader shows it', () => {
        expect(actLabel(1, 40)).toBe('1. vaatus — 40 min');
        expect(actLabel(2, null)).toBe('2. vaatus');
    });
});

describe('readIntermissionForm', () => {
    it('reads both lengths when both are given', () => {
        const form = readIntermissionForm('15', '25', config);

        expect(form).toEqual({
            minutes: 15,
            act: 25,
            errors: { minutes: '', act: '' },
        });
    });

    it('insists on the length of the part the interval ends', () => {
        const form = readIntermissionForm('15', '', config);

        expect(form.act).toBeNull();
        expect(form.errors.act).toContain('1–240');
        // The interval's own length was fine, so it is not complained about.
        expect(form.minutes).toBe(15);
        expect(form.errors.minutes).toBe('');
    });

    it('treats a blank of only whitespace as no answer at all', () => {
        expect(readIntermissionForm('15', '   ', config).act).toBeNull();
    });

    it('refuses lengths outside the range the server takes', () => {
        expect(readIntermissionForm('0', '25', config).minutes).toBeNull();
        expect(readIntermissionForm('61', '25', config).minutes).toBeNull();
        expect(readIntermissionForm('15', '0', config).act).toBeNull();
        expect(readIntermissionForm('15', '241', config).act).toBeNull();
        expect(readIntermissionForm('kaks', 'kolm', config)).toMatchObject({
            minutes: null,
            act: null,
        });
    });

    it('rounds a fractional length down, as the server stores whole minutes', () => {
        expect(readIntermissionForm('15.7', '25.2', config)).toMatchObject({
            minutes: 15,
            act: 25,
        });
    });
});

describe('buildBlocks', () => {
    const scene = (n: number): Scene => blankScene(`stseen-${n}`);

    /** Each block, written as "act 1: 1,2" or "| 15", to compare at a glance. */
    function shape(scenes: Scene[], total: number | null = null): string[] {
        return buildBlocks(scenes, total).map((block) =>
            block.kind === 'act'
                ? `act ${block.act.num} (${block.act.minutes}): ${block.act.scenes
                      .map((entry) => `${entry.num}@${entry.index}`)
                      .join(',')}`
                : `| ${block.minutes}@${block.index}`,
        );
    }

    it('draws a show with no interval as a single half', () => {
        expect(shape([scene(1), scene(2)])).toEqual(['act 1 (null): 1@0,2@1']);
    });

    it('splits the running order at each interval, numbering scenes on', () => {
        const scenes = [
            scene(1),
            scene(2),
            blankIntermission('stseen-3', 15, 40),
            scene(4),
        ];

        expect(shape(scenes, 90)).toEqual([
            'act 1 (40): 1@0,2@1',
            '| 15@2',
            'act 2 (35): 3@3',
        ]);
    });

    it('draws no empty half for an interval at either end', () => {
        const scenes = [
            blankIntermission('stseen-1', 10),
            scene(2),
            blankIntermission('stseen-3', 10),
        ];

        expect(shape(scenes)).toEqual([
            '| 10@0',
            'act 1 (null): 1@1',
            '| 10@2',
        ]);
    });

    it('keys every block uniquely', () => {
        const blocks = buildBlocks(
            [scene(1), blankIntermission('stseen-2', 15), scene(3)],
            null,
        );

        expect(new Set(blocks.map((block) => block.key)).size).toBe(3);
    });
});
