import { describe, expect, it } from 'vitest';
import { blankPlan } from './plan';
import { canSaveDraft, isArchived, isDelivered, isDraft } from './planStatus';

describe('isDelivered', () => {
    it('counts the statuses the technical team is already holding', () => {
        expect(isDelivered('submitted')).toBe(true);
        expect(isDelivered('received')).toBe(true);
    });

    it('leaves out a plan the crew has not been handed', () => {
        // A blank plan is a draft, which is what makes the review step offer to
        // submit rather than to update.
        expect(isDelivered(blankPlan().status)).toBe(false);
        // Archived: its night has been played, so submitting again is a fresh
        // hand-in and the crew is told about it.
        expect(isDelivered('archived')).toBe(false);
    });
});

describe('isDraft', () => {
    it('counts a plan nobody has been handed', () => {
        expect(isDraft('draft')).toBe(true);
        expect(isDraft(blankPlan().status)).toBe(true);
    });

    it('leaves out every status a plan reaches after it is submitted', () => {
        expect(isDraft('submitted')).toBe(false);
        expect(isDraft('received')).toBe(false);
        expect(isDraft('archived')).toBe(false);
        expect(isDraft(null)).toBe(false);
    });
});

describe('isArchived', () => {
    it('counts a plan that has been put away', () => {
        expect(isArchived('archived')).toBe(true);
    });

    it('leaves out every status a plan holds before it is archived', () => {
        expect(isArchived('draft')).toBe(false);
        expect(isArchived('submitted')).toBe(false);
        expect(isArchived('received')).toBe(false);
        expect(isArchived(null)).toBe(false);
    });
});

describe('canSaveDraft', () => {
    it('offers a draft save on a plan that has never been saved', () => {
        expect(canSaveDraft(blankPlan())).toBe(true);
    });

    it('offers one again on a plan saved and left as a draft', () => {
        expect(canSaveDraft({ token: 'R10-2026-abc', status: 'draft' })).toBe(
            true,
        );
    });

    it('stops offering one once the crew is holding the plan', () => {
        expect(
            canSaveDraft({ token: 'R10-2026-abc', status: 'submitted' }),
        ).toBe(false);
        expect(
            canSaveDraft({ token: 'R10-2026-abc', status: 'received' }),
        ).toBe(false);
    });

    it('stops offering one on a plan whose night has been played', () => {
        // Archived is not a draft to go back to: saving one could not put it
        // back to draft anyway — see App\Actions\SaveTechnicalPlan.
        expect(
            canSaveDraft({ token: 'R10-2026-abc', status: 'archived' }),
        ).toBe(false);
    });
});
