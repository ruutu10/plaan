import { describe, expect, it } from 'vitest';
import { blankPlan, hydratePlan } from './plan';

describe('hydratePlan', () => {
    it('keeps the status and submission time a saved plan arrives with', () => {
        const hydrated = hydratePlan({
            token: 'abc123',
            status: 'submitted',
            submittedAt: '2026-07-20T18:00:00+00:00',
        });

        expect(hydrated.status).toBe('submitted');
        expect(hydrated.submittedAt).toBe('2026-07-20T18:00:00+00:00');
    });

    it.each(['draft', 'submitted', 'received', 'archived'])(
        'carries the %s status through untouched',
        (status) => {
            expect(hydratePlan({ token: 'abc123', status }).status).toBe(
                status,
            );
        },
    );

    it('treats a plan that has never reached the server as a draft', () => {
        expect(hydratePlan(null).status).toBe('draft');
        expect(hydratePlan(null).submittedAt).toBeNull();
    });

    it('falls back to the blank plan status when the payload omits one', () => {
        const hydrated = hydratePlan({ token: 'abc123' });

        expect(hydrated.status).toBe(blankPlan().status);
        expect(hydrated.submittedAt).toBeNull();
    });
});
