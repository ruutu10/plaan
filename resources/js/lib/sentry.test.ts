import * as Sentry from '@sentry/vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@sentry/vue', () => ({
    init: vi.fn(),
    getFeedback: vi.fn(),
    browserTracingIntegration: vi.fn(),
    replayIntegration: vi.fn(),
    feedbackIntegration: vi.fn(),
    attachErrorHandler: vi.fn(),
}));

// `feedbackActor` is module-level state captured once in `initializeSentry()`,
// so each case needs its own fresh module instance rather than reusing one.
async function freshSentryModule() {
    vi.resetModules();

    return import('@/lib/sentry');
}

// The module boots the browser SDK only where there is a browser to boot it
// in, and these run in Node. Without a stand-in window every case here would
// pass by never reaching the code it is about.
//
// Vitest's own `import.meta.env.DEV` is true by default — it is, after all,
// running the code for a test, not a production build — so every case that
// isn't itself about the dev-server guard has to say it is a production
// build to reach the behaviour it means to exercise.
beforeEach(() => {
    vi.stubGlobal('window', {});
    vi.stubEnv('DEV', false);
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
    vi.clearAllMocks();
});

describe('hideFeedbackWidget / showFeedbackWidget', () => {
    it('toggles the actor button captured when Sentry initialised', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://example.invalid/1');
        const actor = { show: vi.fn(), hide: vi.fn() };
        vi.mocked(Sentry.getFeedback).mockReturnValue({
            createWidget: vi.fn().mockReturnValue(actor),
        } as never);

        const { initializeSentry, hideFeedbackWidget, showFeedbackWidget } =
            await freshSentryModule();
        initializeSentry();
        hideFeedbackWidget();
        showFeedbackWidget();

        expect(actor.hide).toHaveBeenCalledOnce();
        expect(actor.show).toHaveBeenCalledOnce();
    });

    it('is a no-op when Sentry never initialised, e.g. missing DSN', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', '');

        const { initializeSentry, hideFeedbackWidget, showFeedbackWidget } =
            await freshSentryModule();
        initializeSentry();

        expect(() => hideFeedbackWidget()).not.toThrow();
        expect(() => showFeedbackWidget()).not.toThrow();
        expect(Sentry.getFeedback).not.toHaveBeenCalled();
    });

    it('is a no-op in a dev server even with a DSN configured', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://example.invalid/1');
        vi.stubEnv('DEV', true);

        const { initializeSentry, hideFeedbackWidget, showFeedbackWidget } =
            await freshSentryModule();
        initializeSentry();

        expect(() => hideFeedbackWidget()).not.toThrow();
        expect(() => showFeedbackWidget()).not.toThrow();
        expect(Sentry.init).not.toHaveBeenCalled();
        expect(Sentry.getFeedback).not.toHaveBeenCalled();
    });
});

describe('initializeSentry / attachSentryToVueApp', () => {
    it('does not attach the error handler in a dev server', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://example.invalid/1');
        vi.stubEnv('DEV', true);

        const { attachSentryToVueApp } = await freshSentryModule();
        attachSentryToVueApp({} as never);

        expect(Sentry.attachErrorHandler).not.toHaveBeenCalled();
    });
});
