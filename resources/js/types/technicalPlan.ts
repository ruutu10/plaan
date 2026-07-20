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
    name: string;
    size: number;
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
}

export interface LookupResult {
    token: string;
    title: string;
    sub: string;
}
