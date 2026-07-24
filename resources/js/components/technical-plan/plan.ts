import type { Plan, Scene } from '@/types/technicalPlan';

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
        soundUrl: '',
        sound: '',
        notes: '',
        collapsed: false,
    };
}

export function blankPlan(): Plan {
    return {
        token: null,
        status: 'draft',
        submittedAt: null,
        meta: {
            performanceId: null,
            performer: '',
            showName: '',
            showDate: '',
            duration: null,
            description: '',
        },
        sound: {
            micsMode: 'no',
            micsDetail: '',
            musicianMode: 'no',
            musicianDetail: '',
        },
        scenes: [blankScene()],
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
        status: 'draft',
        submittedAt: null,
        meta: { ...base.meta, ...(payload.meta ?? {}) },
        sound: { ...base.sound, ...(payload.sound ?? {}) },
        scenes:
            payload.scenes && payload.scenes.length
                ? payload.scenes.map((s, index) => ({
                      ...blankScene(),
                      ...s,
                      id: `${SCENE_ID_PREFIX}${index + 1}`,
                      collapsed: false,
                  }))
                : [blankScene()],
        equipment: {
            ...base.equipment,
            ...(payload.equipment ?? {}),
            items: payload.equipment?.items ?? [],
        },
        extra: {
            ...base.extra,
            ...(payload.extra ?? {}),
            files: (payload.extra?.files ?? [])
                .filter((file) => Boolean(file.id))
                .map((file) => ({
                    ...file,
                    status: 'ready' as const,
                })),
        },
    };
}

export function formatFileSize(bytes: number | null | undefined): string {
    if (bytes == null) {
        return '';
    }

    if (bytes < 1024) {
        return bytes + ' B';
    }

    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(0) + ' KB';
    }

    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}
