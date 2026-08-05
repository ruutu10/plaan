import * as Sentry from '@sentry/vue';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@sentry/vue', () => ({
    init: vi.fn(),
    getFeedback: vi.fn(),
    browserTracingIntegration: vi.fn(),
    replayIntegration: vi.fn(),
    feedbackIntegration: vi.fn(),
}));

// `feedbackActor` is module-level state captured once in `initializeSentry()`,
// so each case needs its own fresh module instance rather than reusing one.
async function freshSentryModule() {
    vi.resetModules();
    return import('@/lib/sentry');
}

afterEach(() => {
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
});
