import type {
    PlanDocumentScene,
    PlanDocumentSound,
} from '@/types/technicalPlan';

/**
 * The scene list as it is laid out on paper.
 *
 * On screen the scenes are a five-column table, which is the fastest thing to
 * scan in the booth but is wider than A4: printed, the columns squeeze every cue
 * down to a word a line. So the printout gets the same scenes one under the
 * other instead — a heading per scene, its fields read as lines beneath it — and
 * this is where the table's rows are turned into that running order.
 *
 * The values themselves arrive already rendered by `presentPlan()`; nothing here
 * decides how one reads.
 */
export type PlanPrintBlock =
    /** The heading that opens a part of the show, its length included. */
    | { kind: 'act'; label: string }
    /** The break between two parts, said in full on one line. */
    | { kind: 'intermission'; label: string }
    | { kind: 'scene'; title: string; details: PlanPrintDetail[] };

/** One line beneath a scene's heading: its label, then what follows the colon. */
export interface PlanPrintDetail {
    label: string;
    /** The cues, on the sound line; empty on every other line. */
    sounds: PlanDocumentSound[];
    /** The text after the label — or, on the sound line, after the cues. */
    text: string;
}

/**
 * Flattens the document's scenes into the blocks the printout renders in order.
 *
 * A part heading rides on the scene that opens its part, so it is emitted as a
 * block of its own just before that scene.
 */
export function printBlocks(scenes: PlanDocumentScene[]): PlanPrintBlock[] {
    const blocks: PlanPrintBlock[] = [];

    for (const scene of scenes) {
        if (scene.actLabel) {
            blocks.push({ kind: 'act', label: scene.actLabel });
        }

        if (scene.intermission > 0) {
            blocks.push({ kind: 'intermission', label: scene.name });

            continue;
        }

        blocks.push({
            kind: 'scene',
            title: `${scene.num}. ${scene.name}`,
            details: [
                { label: 'Valgus', sounds: [], text: scene.light },
                { label: 'Heli', sounds: scene.sounds, text: scene.soundText },
                { label: 'Märkmed', sounds: [], text: scene.notes },
            ],
        });
    }

    return blocks;
}
