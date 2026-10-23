import type { PlanFile } from '@/types/technicalPlan';

/**
 * A file handle as it comes back from the server, ready to be shown. Handles
 * without an id never made it server-side and are dropped.
 */
export function storedFile(file: PlanFile | null | undefined): PlanFile | null {
    return file?.id ? { ...file, status: 'ready' as const } : null;
}

/**
 * A handle the wizard has finished uploading — the only kind worth showing.
 *
 * Deliberately permissive about a missing `status`: a handle that arrived from
 * the server carries no upload state of its own, and neither do the fixtures
 * the document renderers are tested against. Only the two states that mean
 * "not there yet" disqualify a file.
 */
export function isReady(file: PlanFile | null | undefined): file is PlanFile {
    return (
        file != null && file.status !== 'uploading' && file.status !== 'error'
    );
}

/**
 * A file size the browser and the mail render identically. Mirrors
 * `PlanDocument::fileSize()`, which deliberately avoids Laravel's
 * locale-formatted `Number::fileSize()` — the two are pinned together by
 * `tests/fixtures/plan-document.json`.
 */
export function formatFileSize(bytes: number | null | undefined): string {
    if (bytes == null) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let size = bytes / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toFixed(1)} ${units[unit]}`;
}
