import type {
    Plan,
    PlanDocument,
    PlanDocumentFile,
    PlanDocumentScene,
    PlanDocumentSound,
    PlanFile,
    SceneSound,
} from '@/types/technicalPlan';
import {
    actLabel,
    formatFileSize,
    intermissionLabel,
    intermissionMinutes,
    isReady,
    showParts,
    soundHasSource,
} from './plan';

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
        location: dash(plan.meta.location),
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
    let num = 0;

    // The evening's shape: which scene opens each part of the show, and how
    // long that part runs. Empty for a show that is not played in parts.
    const parts = new Map(
        showParts(plan.scenes, plan.meta.duration).map((part) => [
            part.start,
            actLabel(part.num, part.minutes),
        ]),
    );

    return plan.scenes.map((scene, index) => {
        const intermission = intermissionMinutes(scene);

        // An interval is not one of the numbered scenes, and it carries no
        // cues: it is a break in the running order, so it is left blank and
        // the scene numbering steps over it.
        if (intermission > 0) {
            return {
                num: 0,
                name: '',
                light: '',
                sounds: [],
                sound: '',
                notes: '',
                intermission,
                actLabel: '',
            };
        }

        num += 1;

        return {
            num,
            name: scene.name.trim(),
            light: scene.light.trim(),
            sounds: normaliseSounds(scene.sounds),
            sound: scene.sound.trim(),
            notes: scene.notes.trim(),
            intermission: 0,
            actLabel: parts.get(index) ?? '',
        };
    });
}

/**
 * A scene's cues, less the ones that are not really there: a file still going
 * up, or one that failed, is not a cue the technician will receive, and an
 * entry left with neither a file nor a link is an empty row nobody meant.
 */
function normaliseSounds(sounds: SceneSound[]): NormalisedSound[] {
    return sounds.filter(soundHasSource).map((sound) => ({
        id: sound.id,
        url: sound.url.trim(),
        file: isReady(sound.file) ? sound.file : null,
    }));
}

export interface NormalisedScene {
    /** Zero on an interval, which is not one of the numbered scenes. */
    num: number;
    name: string;
    light: string;
    sounds: NormalisedSound[];
    sound: string;
    notes: string;
    /** Minutes the interval lasts, or zero on an ordinary scene. */
    intermission: number;
    /** The part of the show this row opens, its length included; else empty. */
    actLabel: string;
}

export interface NormalisedSound {
    id: string;
    url: string;
    file: PlanFile | null;
}

function presentScene(scene: NormalisedScene): PlanDocumentScene {
    // The interval is one line across the table rather than a row of fields,
    // so the whole of it is said in the name and the rest is left empty.
    if (scene.intermission > 0) {
        return {
            num: 0,
            name: intermissionLabel(scene.intermission),
            light: '',
            sounds: [],
            soundText: '',
            notes: '',
            intermission: scene.intermission,
            actLabel: '',
        };
    }

    return {
        num: scene.num,
        name: dash(scene.name),
        light: dash(scene.light),
        sounds: scene.sounds.map(presentSound),
        // The cues get their own lines above this, so the text is only stood in
        // for by a dash when the scene has no sound at all.
        soundText:
            scene.sound !== ''
                ? scene.sound
                : scene.sounds.length > 0
                  ? ''
                  : '—',
        notes: dash(scene.notes),
        intermission: 0,
        actLabel: scene.actLabel,
    };
}

function presentSound(sound: NormalisedSound): PlanDocumentSound {
    return {
        file: sound.file ? presentFile(sound.file) : null,
        url: sound.url,
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
