export interface Scene {
    id: string;
    name: string;
    light: string;
    /** The scene's cues, in the order they are played. */
    sounds: SceneSound[];
    /**
     * How the scene's sound is used, in the performer's own words. One field
     * for the whole scene — the cues themselves carry no description.
     */
    sound: string;
    notes: string;
    collapsed?: boolean;
}

/**
 * One cue: either a link or an uploaded file, never both and never neither.
 * The same file may appear on several scenes — reusing a sting is the point of
 * the picker — so a handle is not a scene's to own.
 */
export interface SceneSound {
    /** Client-side row key, stable across reorders. */
    id: string;
    /** Link to the sound — empty when this entry is an uploaded file. */
    url: string;
    /** The uploaded file — null when this entry is a link. */
    file: PlanFile | null;
}

/**
 * A sound file the performer already has on one of their other plans, offered
 * in the "pick one you have uploaded" step. Picking it copies the file, so `id`
 * names the source rather than the handle this plan will end up carrying.
 */
export interface ReusableSound {
    id: string;
    name: string;
    size: number;
    /** Streams the source file, so it can be auditioned before it is picked. */
    url: string;
    planToken: string | null;
    planLabel: string;
    performanceDate: string | null;
}

export interface EquipItem {
    id: string;
    name: string;
    use: string;
}

export interface PlanFile {
    /** Server-side handle (media UUID) once the upload has finished. */
    id: string;
    name: string;
    size: number;
    /** Streams the file inline (opens in a new browser tab). */
    url?: string;
    /** Forces a download under the original file name. */
    downloadUrl?: string;
    status?: 'uploading' | 'ready' | 'error';
    /** Client-only key used to track a file while it is still uploading. */
    tempKey?: string;
    error?: string;
}

export interface PlanMeta {
    /**
     * The performance the plan is for. Null only until one has been picked —
     * every saved plan names one, the stand-in performance included.
     */
    performanceId: number | null;
    performer: string;
    formatName: string;
    performanceDate: string;
    /** Curtain-up as "19:00", on the venue's clock. */
    startTime: string;
    duration: number | null;
    description: string;
}

export interface PriorPlan {
    /** Token of a past submitted plan that can seed a new one. */
    token: string;
    /** Human label distinguishing the plan (the past performance's date, and its title when it has one). */
    label: string;
    /** Who handed the plan in, when that was not the user themselves. */
    author: string | null;
}

export interface UpcomingPerformance {
    id: number;
    performer: string;
    formatName: string;
    /**
     * The act's own name, when the evening is shared and the format's name alone
     * would leave several identical rows to choose between.
     */
    title: string | null;
    /** ISO date (YYYY-MM-DD), on the venue's clock. */
    performanceDate: string;
    /** Curtain-up as "19:00", on the venue's clock. */
    startTime: string;
    duration: number | null;
    description: string;
    /**
     * Whether a technical plan is expected for this night at all. False for the
     * formats that run themselves; the row says so, and a plan handed in anyway
     * is taken just the same.
     */
    technicalPlanMandatory: boolean;
    /** Plans handed in for other performances of the same format, by the user or their teams. */
    priorPlans: PriorPlan[];
}

export interface PlanSound {
    micsMode: 'no' | 'yes';
    micsDetail: string;
    musicianMode: 'no' | 'yes';
    musicianDetail: string;
}

export interface PlanEquipment {
    items: EquipItem[];
    smoke: 'no' | 'yes';
    suggestions: 'yes' | 'no';
    suggestNote: string;
}

export interface PlanExtra {
    notes: string;
    files: PlanFile[];
}

export interface Plan {
    token: string | null;
    status: string;
    submittedAt: string | null;
    /**
     * The email of whoever handed the plan in — the document's contact, shown
     * to every reader alike. Null while the plan is still being written (it has
     * no author yet) and for a guest on a share link, who is not given it.
     */
    authorEmail: string | null;
    meta: PlanMeta;
    sound: PlanSound;
    scenes: Scene[];
    equipment: PlanEquipment;
    extra: PlanExtra;
}

/**
 * A plan rendered as the document the reader sees: every value already turned
 * into its final string. Produced by `presentPlan()` for the review page, the
 * printout and the technician's playback view, and by `App\Http\Resources\
 * PlanDocument` for the mail. The two shapes are held together by
 * `tests/fixtures/plan-document.json`.
 */
export interface PlanDocumentScene {
    num: number;
    name: string;
    light: string;
    sounds: PlanDocumentSound[];
    /** Empty when the cues already say it; an em dash when there is no sound at all. */
    soundText: string;
    notes: string;
}

/** One cue as the reader sees it: a named file, or a bare link. */
export interface PlanDocumentSound {
    file: PlanDocumentFile | null;
    url: string;
}

export interface PlanDocumentFile {
    name: string;
    sizeLabel: string;
    url?: string | null;
    downloadUrl?: string | null;
}

export interface PlanDocument {
    token: string | null;
    statusLabel: string;
    formatName: string;
    performer: string;
    contact: string;
    performanceDate: string;
    startTime: string;
    durationLabel: string;
    description: string;
    micsSummary: string;
    musicianSummary: string;
    scenes: PlanDocumentScene[];
    equipmentItems: { name: string; use: string }[];
    smokeSummary: string;
    suggestionsLine: string;
    notes: string;
    files: PlanDocumentFile[];
}

export interface WizardConfig {
    deadlineHours: number;
    techEmail: string;
    /** Lower-case file extensions the server accepts, from config/media-library.php. */
    allowedExtensions: string[];
    /** The subset of the above a scene's sound file may use. */
    soundExtensions: string[];
    /** Maximum accepted upload size in bytes. */
    maxFileSize: number;
}

export interface LookupResult {
    token: string;
    title: string;
    sub: string;
}

/** One row of the technical crew's overview of every plan in the house. */
export interface AdminPlanRow {
    token: string;
    formatName: string | null;
    /** The format's own screen, or null when the reader may not open it. */
    formatUrl: string | null;
    teamName: string | null;
    /** ISO 8601 UTC instant the performance starts at, if the plan names one. */
    performanceStartsAt: string | null;
    submittedBy: string | null;
    submittedByEmail: string | null;
    status: string;
    statusLabel: string;
    /** ISO 8601 UTC instant. */
    submittedAt: string | null;
    /** Public link opening the plan itself. */
    url: string;
}

/** One status a plan may be moved to, offered to a picker. */
export interface StatusOption {
    value: string;
    label: string;
}
