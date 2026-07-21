export interface Scene {
    id: string;
    name: string;
    light: string;
    soundUrl: string;
    sound: string;
    notes: string;
    collapsed?: boolean;
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
    contactEmail: string;
}

export interface UpcomingPerformance {
    id: number;
    performer: string;
    showName: string;
    showDate: string;
    duration: number | null;
    description: string;
}

export interface PlanSound {
    micsMode: 'no' | 'yes';
    micsDetail: string;
    musicianMode: 'no' | 'yes';
    musicianDetail: string;
    musicMode: 'none' | 'use';
    musicList: string;
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
    /** Maximum accepted upload size in bytes. */
    maxFileSize: number;
}

export interface LookupResult {
    token: string;
    title: string;
    sub: string;
}
