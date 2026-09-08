import type {
    Plan,
    PlanFile,
    PlanSound,
    Scene,
    SceneSound,
    WizardConfig,
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
 * The break between two halves of the show. It is an entry in the scenes array
 * like any other — that is what keeps it in its place in the running order —
 * carrying nothing but how long it lasts.
 */
export function blankIntermission(
    id: string,
    minutes: number,
    act: number | null = null,
): Scene {
    return {
        ...blankScene(id),
        intermission: minutes,
        actMinutes: act,
        collapsed: true,
    };
}

/**
 * How long an entry's interval lasts, in whole minutes, or zero when the entry
 * is an ordinary scene. The one place the stored value is judged, so the
 * wizard, the document and the technician's view all draw the line in the same
 * place; `App\Http\Resources\TechnicalPlan::intermission()` mirrors it
 * server-side.
 */
export function intermissionMinutes(
    scene: Pick<Scene, 'intermission'>,
): number {
    const minutes = Math.floor(Number(scene.intermission ?? 0));

    return Number.isFinite(minutes) && minutes > 0 ? minutes : 0;
}

/** Whether this entry is an interval rather than a scene. */
export function isIntermission(scene: Pick<Scene, 'intermission'>): boolean {
    return intermissionMinutes(scene) > 0;
}

/** The interval as every reader is shown it, minutes included. */
export function intermissionLabel(minutes: number): string {
    return `Vaheaeg — ${minutes} min`;
}

/**
 * How long the part of the show an interval *ends* runs, as its author wrote
 * it, or null when nobody has said. Only an interval carries this; see
 * {@link showParts}, which is where it is turned into the split the reader
 * sees. `App\Http\Resources\TechnicalPlan::actMinutes()` mirrors it.
 */
export function actMinutes(scene: Pick<Scene, 'actMinutes'>): number | null {
    const minutes = Math.floor(Number(scene.actMinutes ?? 0));

    return Number.isFinite(minutes) && minutes > 0 ? minutes : null;
}

/** One part of a show played in parts — see {@link showParts}. */
export interface ShowPart {
    /** Which part this is, counted from one over the parts a reader sees. */
    num: number;
    /** Where the part opens: the index in `scenes` of its first scene. */
    start: number;
    /** How many minutes it runs, or null when it cannot be known. */
    minutes: number | null;
}

/**
 * How long each part of a show played in parts runs, so the technician can see
 * the evening's shape at a glance rather than working it out at the desk.
 *
 * A part is a run of scenes with an interval beside it. Each interval says how
 * long the part it ends runs; the closing part is not asked, because it is what
 * is left of the evening — the show's own running time, less every interval and
 * every part already named. That subtraction only holds when nothing is
 * missing, so a part whose length cannot be worked out is reported as null
 * rather than guessed at.
 *
 * A show with nothing to split — no interval, or one standing before the first
 * scene or after the last — has no parts at all, and no reader shows a split
 * for it. Mirrored by `App\Http\Resources\TechnicalPlan::showParts()`.
 */
export function showParts(
    scenes: Scene[],
    totalMinutes: number | null,
): ShowPart[] {
    const parts: { start: number; minutes: number | null }[] = [];
    let breaks = 0;
    let open: number | null = null;

    scenes.forEach((scene, index) => {
        const interval = intermissionMinutes(scene);

        if (interval > 0) {
            breaks += interval;

            // An interval with no scenes behind it closes nothing: it stands
            // at the top of the running order, or straight after another.
            if (open !== null) {
                parts.push({ start: open, minutes: actMinutes(scene) });
                open = null;
            }

            return;
        }

        if (open === null) {
            open = index;
        }
    });

    // Whatever the last interval left open closes the show.
    if (open !== null) {
        parts.push({ start: open, minutes: null });
    }

    if (parts.length < 2) {
        return [];
    }

    const named = parts.filter((part) => part.minutes !== null);
    const total = totalMinutes ?? 0;
    const closing = parts[parts.length - 1];

    // The closing part is the only one worth working out, and only while it is
    // the only one left unsaid.
    if (
        closing.minutes === null &&
        total > 0 &&
        named.length === parts.length - 1
    ) {
        const rest =
            total -
            breaks -
            named.reduce((sum, part) => sum + (part.minutes ?? 0), 0);

        closing.minutes = rest > 0 ? rest : null;
    }

    return parts.map((part, index) => ({ num: index + 1, ...part }));
}

/** One part of the show as every reader is shown it, its length included. */
export function actLabel(num: number, minutes: number | null): string {
    return minutes === null
        ? `${num}. vaatus`
        : `${num}. vaatus — ${minutes} min`;
}

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
 * A file handle as it comes back from the server, ready to be shown. Handles
 * without an id never made it server-side and are dropped.
 */
function storedFile(file: PlanFile | null | undefined): PlanFile | null {
    return file?.id ? { ...file, status: 'ready' as const } : null;
}

/**
 * A handle the wizard has finished uploading — the only kind worth showing.
 *
 * Deliberately permissive about a missing `status`: a handle that arrived from
 * the server carries no upload state of its own, and neither do the fixtures
 * the document renderers are tested against. Only the two states that mean
 * "not there yet" disqualify a file.
 */
export function isReady(file: PlanFile | null | undefined): file is PlanFile {
    return (
        file != null && file.status !== 'uploading' && file.status !== 'error'
    );
}

/**
 * Whether a cue points at something real: a file that finished uploading, or a
 * link that was actually typed.
 *
 * The one place this question is answered. It decides what the wizard posts,
 * what it expects back from the save, what the document renders and what
 * survives hydration — and those four have to agree, or a cue is dropped in one
 * place and kept in another.
 */
export function soundHasSource(sound: SceneSound): boolean {
    return isReady(sound.file) || (sound.url ?? '').trim() !== '';
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
 * Whether any cue anywhere in the plan still names this stored file.
 *
 * The same handle may serve several scenes — reusing a sting is the point of
 * the picker — and a *staged* upload is deleted for real rather than swept up
 * later, so letting go of one scene's cue must not take the sound out from
 * under another's. Ask this after the cue has left the plan, so what remains is
 * what is really still wanted.
 */
export function soundFileStillUsed(plan: Plan, id: string): boolean {
    return plan.scenes.some((scene) =>
        scene.sounds.some((sound) => sound.file?.id === id),
    );
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

/**
 * Whether the technical team is already holding this plan — submitted, or
 * confirmed by a technician since. Mirrors `TechnicalPlanStatus::delivered()`:
 * submitting such a plan again is an update to one the crew has, not a first
 * hand-in, and the server mails nobody for it.
 */
export function isDelivered(status: string): boolean {
    return status === 'submitted' || status === 'received';
}

/**
 * Whether a plan is still its author's own work in progress. Mirrors
 * `TechnicalPlanStatus::Draft`: nobody has been told about it, and it is theirs
 * to keep saving until they hand it in.
 */
export function isDraft(status: string | null): boolean {
    return status === 'draft';
}

/**
 * Whether the wizard offers to save this plan as a draft. A plan nobody has
 * saved yet counts, and so does one still sitting in draft.
 *
 * Asked of the draft status rather than of `isDelivered`, so an archived plan —
 * whose night has been played — is not offered a draft save that could not put
 * it back to draft anyway; see App\Actions\SaveTechnicalPlan, which only sets a
 * status on a new plan or on a submission.
 */
export function canSaveDraft(plan: Pick<Plan, 'token' | 'status'>): boolean {
    return !plan.token || isDraft(plan.status);
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
        // A relative URL needs *a* base before it will parse, and only the path
        // is read afterwards — so a stand-in base answers as well as the page's
        // own origin would, without this needing a browser to run in.
        return hasAudioExtension(
            new URL(url, 'https://plaan.invalid').pathname,
        );
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

/**
 * Why a cue's link cannot be used, or `null` when it can.
 *
 * Mirrors the `url:http,https` rule in `StoreTechnicalPlanRequest`, so the
 * wizard refuses a bad link where the performer typed it rather than leaving
 * the save to fail later — and it refuses it for the same reason the server
 * does. The link is rendered as an `href` in the mail, on the printout and in
 * the technician's view, so a `javascript:` or `data:` URL would be somebody
 * else's code running under a reader who only opened a plan.
 *
 * The length limit is the server's own, handed to the wizard in its config
 * rather than written down a second time here.
 */
export function soundLinkError(
    url: string,
    config: WizardConfig,
): string | null {
    const value = url.trim();

    if (value === '') {
        return 'Lisa helifaili link.';
    }

    if (value.length > config.maxSoundUrlLength) {
        return `Link on liiga pikk (max ${config.maxSoundUrlLength} märki).`;
    }

    let parsed: URL;

    try {
        // No base URL on purpose: a cue's link has to stand on its own, so a
        // bare `example.com/lugu.mp3` is as unusable as a relative path.
        parsed = new URL(value);
    } catch {
        return 'Link peab olema täielik aadress, nt https://example.com/muusika.mp3';
    }

    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        return 'Link peab algama http:// või https:// aadressiga.';
    }

    return null;
}
