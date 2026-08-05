import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { formatFileSize, presentPlan, statusLabel } from './presentPlan';
import type { Plan, PlanDocument } from '@/types/technicalPlan';

/**
 * The other half of `tests/Feature/PlanDocumentTest.php`. Both suites read this
 * fixture, so a display rule changed on one side alone fails a test rather than
 * quietly shipping a review page that disagrees with the mail.
 */
interface Fixture {
    fileSizes: Record<string, string>;
    statusLabels: Record<string, string>;
    cases: {
        name: string;
        contact: string;
        plan: Plan;
        expected: PlanDocument;
    }[];
}

const fixture: Fixture = JSON.parse(
    readFileSync(
        new URL(
            '../../../../tests/fixtures/plan-document.json',
            import.meta.url,
        ),
        'utf-8',
    ),
);

describe('presentPlan', () => {
    it.each(
        fixture.cases.map((testCase) => [testCase.name, testCase] as const),
    )('presents %s as the document the reader sees', (_name, testCase) => {
        expect(presentPlan(testCase.plan, testCase.contact)).toEqual(
            testCase.expected,
        );
    });

    it('formats file sizes the same way as the mail', () => {
        for (const [bytes, expected] of Object.entries(fixture.fileSizes)) {
            expect(formatFileSize(Number(bytes)), `for ${bytes} bytes`).toBe(
                expected,
            );
        }
    });

    it('labels every status and falls back to draft', () => {
        for (const [status, expected] of Object.entries(fixture.statusLabels)) {
            expect(statusLabel(status), `for status '${status}'`).toBe(
                expected,
            );
        }
    });

    it('leaves out attachments that never finished uploading', () => {
        const plan = structuredClone(fixture.cases[1].plan);

        plan.extra.files = [
            { id: 'a', name: 'valmis.pdf', size: 1024, status: 'ready' },
            { id: 'b', name: 'pooleli.pdf', size: 1024, status: 'uploading' },
            { id: 'c', name: 'katki.pdf', size: 1024, status: 'error' },
        ];

        expect(presentPlan(plan, null).files).toEqual([
            {
                name: 'valmis.pdf',
                sizeLabel: '1.0 KB',
                url: null,
                downloadUrl: null,
            },
        ]);
    });
});
