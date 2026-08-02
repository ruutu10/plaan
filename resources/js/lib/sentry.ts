import * as Sentry from '@sentry/vue';
import type { App } from 'vue';

const dsn = import.meta.env.VITE_SENTRY_DSN;

function sampleRate(value: string | undefined, fallback: number): number {
    const parsed = Number.parseFloat(value ?? '');

    return Number.isFinite(parsed) ? parsed : fallback;
}

/**
 * Boots the browser SDK. Called before the Inertia app is created so that
 * errors thrown while resolving the initial page are still reported.
 */
export function initializeSentry(): void {
    if (!dsn) {
        return;
    }

    Sentry.init({
        dsn,
        environment:
            import.meta.env.VITE_SENTRY_ENVIRONMENT || import.meta.env.MODE,
        release: import.meta.env.VITE_SENTRY_RELEASE || undefined,
        integrations: [
            // Inertia navigates with the History API, which this instruments out of the box...
            Sentry.browserTracingIntegration(),
            Sentry.replayIntegration(),
            Sentry.feedbackIntegration({
                colorScheme: "system",
                showBranding: false,
                triggerLabel: "Teata veast",
                formTitle: "Teata veast",
                submitButtonLabel: "Saada",
                cancelButtonLabel: "Sulge",
                confirmButtonLabel: "Kinnita",
                addScreenshotButtonLabel: "Lisa ekraanipilt",
                removeScreenshotButtonLabel: "Eemalda ekraanipilt",
                nameLabel: "Sinu nimi",
                namePlaceholder: "",
                messageLabel: "Vea kirjeldus",
                messagePlaceholder: "Mis juhtus? Kuidas süsteem oleks pidanud käituma? Kuidas viga testimiseks korrata?",
                showEmail: false,
                successMessageText: "Edastatud tehnikatiimile"
            }),
        ],
        tracesSampleRate: sampleRate(
            import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE,
            1.0,
        ),
        replaysSessionSampleRate: sampleRate(
            import.meta.env.VITE_SENTRY_REPLAYS_SESSION_SAMPLE_RATE,
            0.1,
        ),
        replaysOnErrorSampleRate: sampleRate(
            import.meta.env.VITE_SENTRY_REPLAYS_ON_ERROR_SAMPLE_RATE,
            1.0,
        ),
        sendDefaultPii: false,
    });
}

/**
 * Attaches Sentry's error handler to the Vue app that Inertia creates for us.
 */
export function attachSentryToVueApp(app: App): void {
    if (!dsn) {
        return;
    }

    Sentry.attachErrorHandler(app, {
        attachProps: true,
        attachErrorHandler: true,
    });
}
