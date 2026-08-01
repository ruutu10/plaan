import type { Auth } from '@/types/auth';
import type { Team } from '@/types/teams';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_SENTRY_DSN?: string;
        readonly VITE_SENTRY_ENVIRONMENT?: string;
        readonly VITE_SENTRY_RELEASE?: string;
        readonly VITE_SENTRY_TRACES_SAMPLE_RATE?: string;
        readonly VITE_SENTRY_REPLAYS_SESSION_SAMPLE_RATE?: string;
        readonly VITE_SENTRY_REPLAYS_ON_ERROR_SAMPLE_RATE?: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            appVersion: string;
            contactEmail: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
