import * as Sentry from '@sentry/vue';
import type { App } from 'vue';

const dsn = import.meta.env.VITE_SENTRY_DSN;

function sampleRate(value: string | undefined, fallback: number): number {
    const parsed = Number.parseFloat(value ?? '');

    return Number.isFinite(parsed) ? parsed : fallback;
}

/**
 * The feedback integration's `remove()`/`createWidget()` pair tears down and
 * rebuilds its shadow DOM host, but `remove()` looks up that host via
 * `ShadowRoot.parentElement` — which is always null, so it never actually
 * detaches anything. Creating the widget ourselves (with `autoInject: false`
 * below) keeps a handle to its actor button instead, whose own `show()` /
 * `hide()` toggle a CSS rule and reliably work.
 */
let feedbackActor: ReturnType<
    NonNullable<ReturnType<typeof Sentry.getFeedback>>['createWidget']
> | null = null;

/**
 * Boots the browser SDK. Called before the Inertia app is created so that
 * errors thrown while resolving the initial page are still reported.
 */
export function initializeSentry(): void {
    if (!dsn || typeof window === 'undefined') {
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
                autoInject: false,
                colorScheme: 'system',
                showBranding: false,
                triggerLabel: 'Teata veast',
                formTitle: 'Teata veast',
                submitButtonLabel: 'Saada',
                cancelButtonLabel: 'Sulge',
                confirmButtonLabel: 'Kinnita',
                addScreenshotButtonLabel: 'Lisa ekraanipilt',
                removeScreenshotButtonLabel: 'Eemalda ekraanipilt',
                nameLabel: 'Sinu nimi',
                namePlaceholder: '',
                messageLabel: 'Vea kirjeldus',
                messagePlaceholder:
                    'Mis juhtus? Kuidas süsteem oleks pidanud käituma? Kuidas viga testimiseks korrata?',
                showEmail: false,
                successMessageText: 'Edastatud tehnikatiimile',
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

    feedbackActor = Sentry.getFeedback()?.createWidget() ?? null;
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

/**
 * Hides the feedback trigger button, e.g. while a full-screen overlay (such
 * as the technician view) covers it and shouldn't be interrupted.
 */
export function hideFeedbackWidget(): void {
    feedbackActor?.hide();
}

/** Shows the feedback trigger button again after {@link hideFeedbackWidget}. */
export function showFeedbackWidget(): void {
    feedbackActor?.show();
}
