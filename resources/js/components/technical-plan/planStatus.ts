import type { Plan } from '@/types/technicalPlan';

/**
 * Whether the technical team is already holding this plan — submitted, or
 * confirmed by a technician since. Mirrors `TechnicalPlanStatus::delivered()`:
 * submitting such a plan again is an update to one the crew has, not a first
 * hand-in, and the server mails nobody for it.
 */
export function isDelivered(status: string): boolean {
    return status === 'submitted' || status === 'received';
}

/**
 * Whether a plan is still its author's own work in progress. Mirrors
 * `TechnicalPlanStatus::Draft`: nobody has been told about it, and it is theirs
 * to keep saving until they hand it in.
 */
export function isDraft(status: string | null): boolean {
    return status === 'draft';
}

/**
 * Whether a plan's night has been played and it has been put away. Mirrors
 * `TechnicalPlanStatus::Archived`.
 */
export function isArchived(status: string | null): boolean {
    return status === 'archived';
}

/**
 * Whether the wizard offers to save this plan as a draft. A plan nobody has
 * saved yet counts, and so does one still sitting in draft.
 *
 * Asked of the draft status rather than of `isDelivered`, so an archived plan —
 * whose night has been played — is not offered a draft save that could not put
 * it back to draft anyway; see App\Actions\SaveTechnicalPlan, which only sets a
 * status on a new plan or on a submission.
 */
export function canSaveDraft(plan: Pick<Plan, 'token' | 'status'>): boolean {
    return !plan.token || isDraft(plan.status);
}
