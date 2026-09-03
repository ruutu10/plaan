import type {
    Plan,
    PlanFile,
    PlanSound,
    Scene,
    SceneSound,
} from '@/types/technicalPlan';

export const STEP_LABELS = [
    'Etendus',
    'Info',
    'Heli',
    'Stseenid',
    'Erivahendid',
    'Lisainfo',
    'Ülevaade',
];

export function uid(): string {
    return 's' + Math.random().toString(36).slice(2, 9);
}

const SCENE_ID_PREFIX = 'stseen-';

/**
 * Build the next sequential scene id (`stseen-1`, `stseen-2`, …) based on the
 * highest number already used, so ids stay unique after reorders and deletes.
 */
export function nextSceneId(scenes: Scene[]): string {
    const highest = scenes.reduce((max, scene) => {
        const match = /^stseen-(\d+)$/.exec(scene.id);

        return match ? Math.max(max, Number(match[1])) : max;
    }, 0);

    return `${SCENE_ID_PREFIX}${highest + 1}`;
}

export function blankScene(id: string = `${SCENE_ID_PREFIX}1`): Scene {
    return {
        id,
        name: '',
        light: '',
        sounds: [],
        sound: '',
        notes: '',
        collapsed: false,
    };
}

const SOUND_ID_PREFIX = 'heli-';

/**
 * How many cues one scene may carry. Mirrors
 * `StoreTechnicalPlanRequest::MAX_SOUNDS_PER_SCENE`, so the wizard stops
 * offering the button before the server would refuse the plan.
 */
export const MAX_SOUNDS_PER_SCENE = 10;

/**
 * The one-click sound cues offered under the scene's description. Shared by the
 * scene card and the add-a-sound dialog, which write to the same field.
 */
export const SOUND_PRESETS = [
    'ruutu10 tunnus 3s',
    'ruutu10 tunnus 15s',
    'film noare (vabal valikul)',
    'shakespeare (vabal valikul)',
];

/**
 * Build the next sequential cue id (`heli-1`, `heli-2`, …) within one scene,
 * on the same footing as `nextSceneId()`: ids stay unique after reorders and
 * deletes, which is what keeps Vue's list keys honest while a row is dragged.
 */
export function nextSoundId(sounds: SceneSound[]): string {
    const highest = sounds.reduce((max, sound) => {
        const match = /^heli-(\d+)$/.exec(sound.id);

        return match ? Math.max(max, Number(match[1])) : max;
    }, 0);

    return `${SOUND_ID_PREFIX}${highest + 1}`;
}

/**
 * The sound step's answers that are still owed a description. Saying "jah" to
 * either question is only half an answer — a microphone nobody has counted or
 * an instrument nobody has named tells the technician nothing — so the detail
 * behind a "jah" is the one thing the step insists on. Mirrors the
 * `required_if` rules in `StoreTechnicalPlanRequest`.
 */
export function soundErrors(sound: PlanSound): {
    micsDetail: string;
    musicianDetail: string;
} {
    return {
        micsDetail:
            sound.micsMode === 'yes' && !sound.micsDetail.trim()
                ? 'Kirjelda mikrofonide kogust ja paigutust laval.'
                : '',
        musicianDetail:
            sound.musicianMode === 'yes' && !sound.musicianDetail.trim()
                ? 'Kirjelda instrumenti ja muusiku paigutust laval.'
                : '',
    };
}

/** Whether the sound step has an unanswered "jah" holding the wizard up. */
export function hasSoundErrors(sound: PlanSound): boolean {
    return Object.values(soundErrors(sound)).some(Boolean);
}

/**
 * A file handle as it comes back from the server, ready to be shown. Handles
 * without an id never made it server-side and are dropped.
 */
function storedFile(file: PlanFile | null | undefined): PlanFile | null {
    return file?.id ? { ...file, status: 'ready' as const } : null;
}

/**
 * A scene's cues as they come back from the server, each given the row id the
 * wizard keys its list on. An entry left with neither a file nor a link is
 * dropped: it would show as an empty row nobody could fill in.
 */
function storedSounds(sounds: SceneSound[] | null | undefined): SceneSound[] {
    return (sounds ?? [])
        .map((sound, index) => ({
            id: sound.id || `heli-${index + 1}`,
            url: sound.url ?? '',
            file: storedFile(sound.file),
        }))
        .filter((sound) => sound.file !== null || sound.url.trim() !== '');
}

/**
 * Every blank plan opens and closes on the same template, most shows have these
 */
function defaultScenes(): Scene[] {
    return [
        {
            ...blankScene(`${SCENE_ID_PREFIX}1`),
            name: 'Lavale tulek',
            light: 'üldvalgus',
            sound: 'Vabalt valitud energiline muusika',
            notes: 'Õhtujuht kutsub esinejad lavale',
        },
        {
            ...blankScene(`${SCENE_ID_PREFIX}2`),
            name: 'Stseenid',
            light: 'üldvalgus',
        },
        {
            ...blankScene(`${SCENE_ID_PREFIX}3`),
            name: 'Lavalt äraminek',
            light: 'üldvalgus',
            notes: 'Kui aeg saab otsa - kummardus, lavalt mahaminek',
        },
    ];
}

export function blankPlan(): Plan {
    return {
        token: null,
        status: 'draft',
        submittedAt: null,
        authorEmail: null,
        meta: {
            performanceId: null,
            performer: '',
            formatName: '',
            performanceDate: '',
            startTime: '',
            duration: null,
            description: '',
        },
        sound: {
            micsMode: 'no',
            micsDetail: '',
            musicianMode: 'no',
            musicianDetail: '',
        },
        scenes: defaultScenes(),
        equipment: {
            items: [],
            smoke: 'yes',
            suggestions: 'yes',
            suggestNote: '',
        },
        extra: {
            notes: '',
            files: [],
        },
    };
}

/**
 * Merge an incoming block onto its blank defaults, ignoring keys that arrived
 * empty. Every wizard field is optional server-side, so a stored plan can carry
 * `null` where the wizard's `Plan` shape promises a string — letting those
 * through would blow up the first `.trim()` that touches them.
 */
function mergeDefined<T extends object>(
    base: T,
    incoming: Partial<T> | null | undefined,
): T {
    const defined = Object.fromEntries(
        Object.entries(incoming ?? {}).filter(([, value]) => value != null),
    ) as Partial<T>;

    return { ...base, ...defined };
}

/**
 * Merge an incoming (possibly partial) payload onto a blank plan so the
 * wizard always has every field present.
 */
export function hydratePlan(payload: Partial<Plan> | null | undefined): Plan {
    const base = blankPlan();

    if (!payload) {
        return base;
    }

    return {
        token: payload.token ?? null,
        // A saved plan keeps the status it was loaded with; only a plan that
        // has never reached the server is a draft.
        status: payload.status ?? base.status,
        submittedAt: payload.submittedAt ?? null,
        authorEmail: payload.authorEmail ?? null,
        meta: mergeDefined(base.meta, payload.meta),
        sound: mergeDefined(base.sound, payload.sound),
        scenes:
            payload.scenes && payload.scenes.length
                ? payload.scenes.map((s, index) => ({
                      ...mergeDefined(blankScene(), s),
                      id: `${SCENE_ID_PREFIX}${index + 1}`,
                      sounds: storedSounds(s.sounds),
                      collapsed: false,
                  }))
                : [blankScene()],
        equipment: {
            ...mergeDefined(base.equipment, payload.equipment),
            items: (payload.equipment?.items ?? []).map((item) => ({
                ...mergeDefined({ id: '', name: '', use: '' }, item),
                // The id is the row's list key, so it must never be blank.
                id: item.id || uid(),
            })),
        },
        extra: {
            ...mergeDefined(base.extra, payload.extra),
            files: (payload.extra?.files ?? [])
                .map(storedFile)
                .filter((file): file is PlanFile => file !== null),
        },
    };
}

/**
 * Reset a plan's content (sound, scenes, equipment, extra) to a blank slate
 * while leaving its performance meta untouched — used when starting a fresh
 * plan for the selected performance.
 */
export function resetPlanContent(plan: Plan): void {
    const blank = blankPlan();

    plan.sound = blank.sound;
    plan.scenes = blank.scenes;
    plan.equipment = blank.equipment;
    plan.extra = blank.extra;
}

/**
 * Copy the content from a source plan onto the current one, keeping the
 * selected performance meta. Files come from the copy endpoint as freshly
 * staged duplicates (their own handles), so they carry across as-is.
 */
export function applyPlanContent(plan: Plan, source: Partial<Plan>): void {
    const hydrated = hydratePlan(source);

    plan.sound = hydrated.sound;
    plan.scenes = hydrated.scenes;
    plan.equipment = hydrated.equipment;
    plan.extra = hydrated.extra;
}

/**
 * A file size the browser and the mail render identically. Mirrors
 * `PlanDocument::fileSize()`, which deliberately avoids Laravel's
 * locale-formatted `Number::fileSize()` — the two are pinned together by
 * `tests/fixtures/plan-document.json`.
 */
export function formatFileSize(bytes: number | null | undefined): string {
    if (bytes == null) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let size = bytes / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toFixed(1)} ${units[unit]}`;
}

/**
 * Extensions a browser can decode into a waveform. Wider than the upload
 * allowlist in `config/technical_plan.php`, because a linked file was never
 * ours to constrain — anything the browser plays is worth showing.
 */
const AUDIO_EXTENSIONS = [
    'mp3',
    'wav',
    'ogg',
    'oga',
    'm4a',
    'aac',
    'flac',
    'opus',
    'weba',
    'aif',
    'aiff',
];

/** Whether a file name ends in an extension a browser can decode. */
function hasAudioExtension(name: string): boolean {
    const extension = name.split('.').pop()?.toLowerCase() ?? '';

    return AUDIO_EXTENSIONS.includes(extension);
}

/**
 * Whether a URL points straight at an audio file. Only the path decides: a
 * sharing page (YouTube, Google Drive, …) names no audio file, and a query
 * string that happens to mention one (say `?file=cue.mp3`) is not the resource
 * being fetched. Such links can only be opened, never played.
 */
function isDirectAudioUrl(url: string): boolean {
    try {
        // A relative URL needs a base before it will parse.
        return hasAudioExtension(new URL(url, window.location.origin).pathname);
    } catch {
        return false;
    }
}

/**
 * The URL a cue can actually be played from, or `null` when it is only
 * reachable through a link a player cannot read. An upload is judged by its
 * stored name — the URL that streams it carries no extension — while a link is
 * judged by its path.
 */
export function soundAudioUrl(sound: SceneSound): string | null {
    const file = sound.file?.status === 'ready' ? sound.file : null;

    if (file?.url && hasAudioExtension(file.name)) {
        return file.url;
    }

    const url = sound.url.trim();

    return url && isDirectAudioUrl(url) ? url : null;
}
