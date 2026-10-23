import type {
    Plan,
    PlanFile,
    PlanSound,
    Scene,
    SceneSound,
    WizardConfig,
} from '@/types/technicalPlan';
import { storedFile } from './files';
import { soundHasSource } from './sceneSounds';

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

export const SCENE_ID_PREFIX = 'stseen-';
export const SOUND_ID_PREFIX = 'heli-';

/**
 * Build the next sequential id under a prefix (`stseen-1`, `heli-2`, …) from the
 * highest number already used, so ids stay unique after reorders and deletes —
 * which is what keeps Vue's list keys honest while a row is being dragged.
 *
 * The pattern is built from the prefix rather than written out beside it, so
 * the two cannot drift apart.
 */
export function nextSequentialId(ids: string[], prefix: string): string {
    const pattern = new RegExp(`^${prefix}(\\d+)$`);

    const highest = ids.reduce((max, id) => {
        const match = pattern.exec(id);

        return match ? Math.max(max, Number(match[1])) : max;
    }, 0);

    return `${prefix}${highest + 1}`;
}

export function nextSceneId(scenes: Scene[]): string {
    return nextSequentialId(
        scenes.map((scene) => scene.id),
        SCENE_ID_PREFIX,
    );
}

/** The next cue id within one scene — see {@see nextSequentialId}. */
export function nextSoundId(sounds: SceneSound[]): string {
    return nextSequentialId(
        sounds.map((sound) => sound.id),
        SOUND_ID_PREFIX,
    );
}

/**
 * Close every scene card. A plan of a dozen scenes is unreadable with all of
 * them open, so the step opens on the list of headings and the scene being
 * worked on is the one opened by hand.
 */
export function collapseScenes(scenes: Scene[]): void {
    scenes.forEach((scene) => {
        scene.collapsed = true;
    });
}

export function blankScene(id: string = `${SCENE_ID_PREFIX}1`): Scene {
    return {
        id,
        name: '',
        light: '',
        sounds: [],
        sound: '',
        notes: '',
        intermission: null,
        actMinutes: null,
        collapsed: false,
    };
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
 * Whether the technician may use smoke at the venue this plan is filed under.
 *
 * The halls that cannot take it are named server-side in
 * `config/technical_plan.php` and handed to the wizard, because the rule
 * belongs to the house rather than to the browser. A performance's location is
 * free text as the board writes it, so each name is looked for anywhere inside
 * it: "improkeskus" catches "Tartu improkeskus" and "improkeskuse BB" too.
 *
 * A night with no location named is still asked the question. An absent venue
 * is an unknown one, not the house's own room, and guessing "no" there would
 * take away a choice the hall might well allow.
 */
export function isSmokeAllowedAt(
    location: string,
    config: Pick<WizardConfig, 'smokeNotPossible'>,
): boolean {
    const venue = location.trim().toLowerCase();

    if (venue === '') {
        return true;
    }

    return !config.smokeNotPossible.some((name) =>
        venue.includes(name.trim().toLowerCase()),
    );
}

/**
 * A scene's cues as they come back from the server, each given the row id the
 * wizard keys its list on. An entry left with neither a file nor a link is
 * dropped: it would show as an empty row nobody could fill in.
 */
function storedSounds(sounds: SceneSound[] | null | undefined): SceneSound[] {
    return (sounds ?? [])
        .map((sound, index) => ({
            id: sound.id || `${SOUND_ID_PREFIX}${index + 1}`,
            url: sound.url ?? '',
            file: storedFile(sound.file),
        }))
        .filter(soundHasSource);
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
            location: '',
            duration: null,
            description: '',
            technicians: [],
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
