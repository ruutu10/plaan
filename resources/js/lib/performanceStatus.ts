import type { PerformanceStatus } from '@/types';

/**
 * How each standing is named on screen. Mirrors `App\Enums\PerformanceStatus`;
 * the server sends the bare value and the labels live here, so the picker in
 * the performance dialog and the pills in every listing read the same.
 *
 * "Ülevaatamata" rather than "mustand" for a draft: it is the word the house
 * has been reading on these rows all along, and it says what is actually
 * being asked of whoever sees it.
 */
const LABELS: Record<PerformanceStatus, string> = {
    draft: 'Ülevaatamata',
    upcoming: 'Tulevane',
    archived: 'Arhiveeritud',
};

/** The standing as a person reads it. */
export function performanceStatusLabel(status: PerformanceStatus): string {
    return LABELS[status];
}

/**
 * Whether the wizard will take this performance when a link names it: one the
 * house has vouched for and whose night is still to come. Mirrors the check in
 * `TechnicalPlanController::linkedPerformance()`, which quietly drops any other.
 */
export function acceptsNewPlan(
    performance: { status: PerformanceStatus; startsAt: string },
    now: Date = new Date(),
): boolean {
    return performance.status !== 'draft' && isStillToCome(performance, now);
}

/** Whether the performance's night has not started yet. */
export function isStillToCome(
    performance: { startsAt: string },
    now: Date = new Date(),
): boolean {
    return new Date(performance.startsAt).getTime() > now.getTime();
}

/** Every standing as a value/label pair, for the dialog's picker. */
export function performanceStatusOptions(): {
    value: PerformanceStatus;
    label: string;
}[] {
    return (Object.keys(LABELS) as PerformanceStatus[]).map((status) => ({
        value: status,
        label: LABELS[status],
    }));
}
