import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import type { Plan, PlanDocument } from '@/types/technicalPlan';
import { presentPlan } from './presentPlan';
import { printBlocks } from './printPlan';

interface Fixture {
    cases: {
        name: string;
        contact: string;
        plan: Plan;
        expected: PlanDocument;
    }[];
}

const fixture: Fixture = JSON.parse(
    readFileSync(
        new URL(
            '../../../../tests/fixtures/plan-document.json',
            import.meta.url,
        ),
        'utf-8',
    ),
);

/** The first fixture case is the show played in two parts, with an interval. */
const doc = presentPlan(fixture.cases[0].plan, fixture.cases[0].contact);

describe('printBlocks', () => {
    it('keeps the running order of the table it stands in for', () => {
        expect(
            printBlocks(doc.scenes).map((block) =>
                block.kind === 'scene' ? block.title : block.label,
            ),
        ).toEqual([
            doc.scenes[0].actLabel,
            `1. ${doc.scenes[0].name}`,
            `2. ${doc.scenes[1].name}`,
            `3. ${doc.scenes[2].name}`,
            doc.scenes[3].name,
            doc.scenes[4].actLabel,
            `4. ${doc.scenes[4].name}`,
            `5. ${doc.scenes[5].name}`,
        ]);
    });

    it('heads a part with a block of its own, before the scene that opens it', () => {
        const blocks = printBlocks(doc.scenes);

        expect(blocks[0]).toEqual({ kind: 'act', label: '1. vaatus — 40 min' });
        expect(blocks[1].kind).toBe('scene');
    });

    it('gives the interval one line and no fields', () => {
        expect(printBlocks(doc.scenes)[4]).toEqual({
            kind: 'intermission',
            label: doc.scenes[3].name,
        });
    });

    it('reads a scene as its three labelled lines, cues on the sound line', () => {
        const scene = doc.scenes[0];
        const block = printBlocks(doc.scenes)[1];

        expect(block).toEqual({
            kind: 'scene',
            title: `1. ${scene.name}`,
            details: [
                { label: 'Valgus', sounds: [], text: scene.light },
                { label: 'Heli', sounds: scene.sounds, text: scene.soundText },
                { label: 'Märkmed', sounds: [], text: scene.notes },
            ],
        });
    });

    it('heads nothing on a show that is not played in parts', () => {
        const plain = doc.scenes
            .filter((scene) => !scene.intermission)
            .map((scene) => ({ ...scene, actLabel: '' }));

        expect(
            printBlocks(plain).every((block) => block.kind === 'scene'),
        ).toBe(true);
    });
});
