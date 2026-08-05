import type {
    Plan,
    PlanDocument,
    PlanDocumentFile,
    PlanDocumentScene,
    PlanFile,
} from '@/types/technicalPlan';
import { formatFileSize } from './plan';

export { formatFileSize };

/**
 * Turn a plan into the document the reader sees: the em dash for a blank field,
 * "Jah — detail", the smoke wording, file sizes, scene numbering.
 *
 * The mail renders the same document from `App\Http\Resources\PlanDocument`.
 * The wizard cannot reuse that one — its review page renders a plan that has
 * not been saved yet — so the rules are mirrored here, and the two are held
 * together by `tests/fixtures/plan-document.json`, which both are asserted
 * against. Change a rule here and the PHP suite fails, and the other way round.
 */
export function presentPlan(plan: Plan, contact: string | null): PlanDocument {
    return {
        token: plan.token,
        statusLabel: statusLabel(plan.status),

        formatName: dash(plan.meta.formatName),
        performer: dash(plan.meta.performer),
        contact: dash(contact),
        performanceDate: dash(plan.meta.performanceDate),
        startTime: dash(plan.meta.startTime),
        durationLabel: duration(plan.meta.duration),
        description: dash(plan.meta.description),

        micsSummary: answer(plan.sound.micsMode, plan.sound.micsDetail),
        musicianSummary: answer(
            plan.sound.musicianMode,
            plan.sound.musicianDetail,
        ),

        scenes: normaliseScenes(plan).map(presentScene),

        equipmentItems: plan.equipment.items
            .filter((item) => item.name.trim() !== '' || item.use.trim() !== '')
            .map((item) => ({ name: dash(item.name), use: dash(item.use) })),
        smokeSummary: smoke(plan.equipment.smoke),
        suggestionsLine: suggestions(
            plan.equipment.suggestions,
            plan.equipment.suggestNote,
        ),

        notes: dash(plan.extra.notes),
        // A file still uploading, or one that failed, is not an attachment the
        // technician will receive — the document lists what was actually sent.
        files: plan.extra.files.filter(isReady).map(presentFile),
    };
}

/** A value the plan may have left empty, shown as an em dash. */
export function dash(value: unknown): string {
    const text = value == null ? '' : String(value).trim();

    return text !== '' ? text : '—';
}

/**
 * A "yes/no" answer and its free-text detail, on one line. Used for the two
 * sound questions.
 */
export function answer(mode: unknown, detail: unknown): string {
    if (mode !== 'yes') {
        return 'Ei';
    }

    const text = detail == null ? '' : String(detail).trim();

    return text !== '' ? `Jah — ${text}` : 'Jah';
}

/**
 * Whether the performer wants the technician's suggestions, and what they wrote
 * alongside. Unlike `answer()` the note is kept even on a "no": someone
 * declining suggestions and then explaining why is telling the technician
 * something worth reading.
 */
export function suggestions(mode: unknown, note: unknown): string {
    const text = note == null ? '' : String(note).trim();

    return (mode === 'yes' ? 'Jah' : 'Ei') + (text !== '' ? ` — ${text}` : '');
}

export function duration(minutes: number | null | undefined): string {
    return minutes ? `${minutes} min` : '—';
}

export function smoke(value: unknown): string {
    if (value === 'no') {
        return 'Ei tohi';
    }

    return value === 'yes' ? 'Jah' : 'Jah, kuid minimaalselt';
}

const STATUS_LABELS: Record<string, string> = {
    draft: 'Mustand',
    submitted: 'Esitatud',
    received: 'Tehniku kinnitatud',
    archived: 'Arhiveeritud',
};

/**
 * The plan's status in the reader's words. An unknown value falls back to the
 * draft label, which is what an unsaved plan in the wizard is.
 */
export function statusLabel(status: string | null | undefined): string {
    return STATUS_LABELS[status ?? ''] ?? STATUS_LABELS.draft;
}

/**
 * How much a status should stand out in a listing: a plan waiting to be picked
 * up is the one worth noticing, a draft or an archived one is not. Keyed to
 * {@see R10Pill}'s tones, so every screen shows a status the same way.
 */
export function statusTone(
    status: string | null | undefined,
): 'muted' | 'neutral' | 'accent' | 'navy' {
    const tones = {
        draft: 'neutral',
        submitted: 'accent',
        received: 'navy',
        archived: 'muted',
    } as const;

    return tones[(status ?? '') as keyof typeof tones] ?? 'neutral';
}

/** A handle the wizard has finished uploading — the only kind worth showing. */
function isReady(file: PlanFile | null | undefined): file is PlanFile {
    return (
        file != null && file.status !== 'uploading' && file.status !== 'error'
    );
}

/**
 * A scene's stored values, tidied but not yet dressed up: numbered as the
 * reader counts them, trimmed, and with a half-finished upload treated as no
 * file at all.
 *
 * This is the part the technician's playback view shares with the document.
 * It stops short of the em dash and the rest of the document's wording,
 * because the playback view leaves an empty cue genuinely empty rather than
 * standing it in — a dash read out over headset means nothing.
 */
export function normaliseScenes(plan: Plan): NormalisedScene[] {
    return plan.scenes.map((scene, index) => ({
        num: index + 1,
        name: scene.name.trim(),
        light: scene.light.trim(),
        soundUrl: scene.soundUrl.trim(),
        soundFile: isReady(scene.soundFile) ? scene.soundFile : null,
        sound: scene.sound.trim(),
        notes: scene.notes.trim(),
    }));
}

export interface NormalisedScene {
    num: number;
    name: string;
    light: string;
    soundUrl: string;
    soundFile: PlanFile | null;
    sound: string;
    notes: string;
}

function presentScene(scene: NormalisedScene): PlanDocumentScene {
    return {
        num: scene.num,
        name: dash(scene.name),
        light: dash(scene.light),
        soundFile: scene.soundFile ? presentFile(scene.soundFile) : null,
        soundUrl: scene.soundUrl,
        // The file and the link get their own lines above this, so the text is
        // only stood in for by a dash when the scene has no sound at all.
        soundText:
            scene.sound !== ''
                ? scene.sound
                : scene.soundFile || scene.soundUrl !== ''
                  ? ''
                  : '—',
        notes: dash(scene.notes),
    };
}

function presentFile(file: PlanFile): PlanDocumentFile {
    return {
        name: file.name,
        sizeLabel: formatFileSize(file.size),
        url: file.url ?? null,
        downloadUrl: file.downloadUrl ?? null,
    };
}
