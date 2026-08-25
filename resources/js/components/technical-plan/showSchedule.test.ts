import { describe, expect, it } from 'vitest';
import type { PlanMeta } from '@/types/technicalPlan';
import { showSchedule } from './showSchedule';

function meta(overrides: Partial<PlanMeta> = {}): PlanMeta {
    return {
        performanceId: 1,
        performer: 'Improgrupp',
        formatName: 'Lühivormid',
        performanceDate: '2026-07-15',
        startTime: '19:00',
        duration: 75,
        description: '',
        ...overrides,
    };
}

// A July evening in Tallinn runs three hours ahead of UTC (DST), so 19:00 in
// the house is the 16:00Z the moments below are written as.
describe('showSchedule', () => {
    it('ends the slot a duration after curtain-up', () => {
        const schedule = showSchedule(meta(), new Date('2026-07-15T16:30:00Z'));

        expect(schedule).toEqual({
            start: '19:00',
            end: '20:15',
            overrunning: false,
        });
    });

    it('leaves the end open when nobody gave a duration', () => {
        const schedule = showSchedule(
            meta({ duration: null }),
            new Date('2026-07-15T20:00:00Z'),
        );

        expect(schedule).toEqual({
            start: '19:00',
            end: null,
            overrunning: false,
        });
    });

    it('has nothing to show without a start time', () => {
        expect(showSchedule(meta({ startTime: '' }), new Date())).toBeNull();
    });

    it('calls the show overrunning once the venue clock is past the end', () => {
        // 17:15:01 UTC is 20:15:01 in Tallinn — a second past the slot.
        expect(
            showSchedule(meta(), new Date('2026-07-15T17:15:01Z'))?.overrunning,
        ).toBe(true);
    });

    it('holds off while the show is still inside its slot', () => {
        // 20:15:00 on the venue clock is the end, not past it.
        expect(
            showSchedule(meta(), new Date('2026-07-15T17:15:00Z'))?.overrunning,
        ).toBe(false);
    });

    it('reads a late show over the venue midnight, not the UTC one', () => {
        const lateShow = meta({ startTime: '23:30', duration: 60 });

        expect(showSchedule(lateShow, new Date('2026-07-15T20:45:00Z'))) // 23:45 venue
            .toEqual({ start: '23:30', end: '00:30', overrunning: false });

        // 21:45 UTC is 00:45 on 16 July in Tallinn — a quarter hour over.
        expect(
            showSchedule(lateShow, new Date('2026-07-15T21:45:00Z'))
                ?.overrunning,
        ).toBe(true);
    });

    it('does not hold a plan without a date to any end time', () => {
        const schedule = showSchedule(
            meta({ performanceDate: '' }),
            new Date('2026-07-15T22:00:00Z'),
        );

        expect(schedule).toEqual({
            start: '19:00',
            end: '20:15',
            overrunning: false,
        });
    });
});
