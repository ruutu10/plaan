export interface Scene {
    id: string;
    name: string;
    light: string;
    /** Link to the scene's sound file — mutually exclusive with `soundFile`. */
    soundUrl: string;
    /** The scene's uploaded sound file (at most one), if not linked instead. */
    soundFile: PlanFile | null;
    sound: string;
    notes: string;
    collapsed?: boolean;
    /** View state: the upload option is open instead of the link field. */
    soundUpload?: boolean;
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
    performanceId: number | null;
    performer: string;
    showName: string;
    showDate: string;
    duration: number | null;
    description: string;
}

export interface PriorPlan {
    /** Token of a past submitted plan that can seed a new one. */
    token: string;
    /** Human label distinguishing the plan (the past performance's date). */
    label: string;
    /** Who handed the plan in, when that was not the user themselves. */
    author: string | null;
}

export interface UpcomingPerformance {
    id: number;
    performer: string;
    showName: string;
    /**
     * The act's own name, when the evening is shared and the show's name alone
     * would leave several identical rows to choose between.
     */
    title: string | null;
    /** ISO date (YYYY-MM-DD), on the venue's clock. */
    showDate: string;
    /** Curtain-up as "19:00", on the venue's clock. */
    startTime: string;
    duration: number | null;
    description: string;
    /** Plans handed in for other performances of the same show, by the user or their teams. */
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
    soundFile: PlanDocumentFile | null;
    soundUrl: string;
    /** Empty when the file or the link already says it; an em dash when there is no sound at all. */
    soundText: string;
    notes: string;
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
    showName: string;
    performer: string;
    contact: string;
    showDate: string;
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
    showName: string | null;
    teamName: string | null;
    /** ISO date (YYYY-MM-DD) of the performance, if the plan names one. */
    performanceDate: string | null;
    submittedBy: string | null;
    submittedByEmail: string | null;
    status: string;
    statusLabel: string;
    submittedAt: string | null;
    /** Public link opening the plan itself. */
    url: string;
}

/** One status a plan may be moved to, offered to a picker. */
export interface StatusOption {
    value: string;
    label: string;
}
