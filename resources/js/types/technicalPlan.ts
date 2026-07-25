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
    /** Human label distinguishing the plan (the past staging's date). */
    label: string;
    /** Who handed the plan in, when that was not the user themselves. */
    author: string | null;
}

export interface UpcomingPerformance {
    id: number;
    performer: string;
    showName: string;
    showDate: string;
    duration: number | null;
    description: string;
    /** Plans handed in for other stagings of the same show, by the user or their teams. */
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
