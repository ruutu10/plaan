import type { PlanFile, WizardConfig } from '@/types/technicalPlan';
import { formatFileSize } from './plan';

/**
 * Shared file-upload plumbing for the wizard. Files are uploaded on their own
 * (staged server-side) and only referenced by handle in the plan, so every step
 * that offers an upload — the plan's own attachments, a scene's sound file —
 * talks to the same endpoints in the same way.
 */

function csrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function headers(): Record<string, string> {
    return {
        Accept: 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

/** The `accept` attribute for a file input limited to these extensions. */
export function acceptAttribute(extensions: string[]): string {
    return extensions.map((extension) => '.' + extension).join(',');
}

/** The extensions spelled out for the user, e.g. "MP3, WAV, OGG". */
export function extensionHint(extensions: string[]): string {
    return extensions.join(', ').toUpperCase();
}

function extensionOf(name: string): string {
    const parts = name.split('.');

    return parts.length > 1 ? parts.pop()!.toLowerCase() : '';
}

/**
 * Reject a file the server would reject anyway, so the user is told before the
 * bytes go over the wire.
 */
export function validationError(
    file: File,
    config: WizardConfig,
    extensions: string[],
): string | null {
    if (file.size > config.maxFileSize) {
        return `Fail on liiga suur (max ${formatFileSize(config.maxFileSize)}).`;
    }

    if (!extensions.includes(extensionOf(file.name))) {
        return 'Seda failitüüpi ei lubata.';
    }

    return null;
}

/**
 * Stage a file server-side and answer with the handle it got — or with an entry
 * marked as failed, carrying the message to show the user.
 *
 * `collection` names the collection the file is destined for (e.g. `sound`),
 * which the server holds the upload to a narrower allowlist for.
 */
export async function uploadAttachment(
    file: File,
    collection?: string,
): Promise<PlanFile> {
    const failed = (error: string): PlanFile => ({
        id: '',
        name: file.name,
        size: file.size,
        status: 'error',
        error,
    });

    const body = new FormData();
    body.append('file', file);

    if (collection) {
        body.append('collection', collection);
    }

    try {
        const response = await fetch('/api/attachments', {
            method: 'POST',
            headers: headers(),
            body,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            return failed(
                (data.message as string) ?? 'Üleslaadimine ebaõnnestus.',
            );
        }

        return {
            id: data.id as string,
            name: (data.name as string) ?? file.name,
            size: (data.size as number) ?? file.size,
            url: data.url as string,
            downloadUrl: data.downloadUrl as string,
            status: 'ready',
        };
    } catch {
        return failed('Üleslaadimine ebaõnnestus.');
    }
}

/**
 * Drop a staged upload server-side. Files already attached to a saved plan are
 * cleaned up when the plan is next saved, so this is best-effort.
 */
export async function discardAttachment(id: string): Promise<void> {
    if (!id) {
        return;
    }

    try {
        await fetch('/api/attachments/' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: headers(),
        });
    } catch {
        /* best-effort cleanup */
    }
}
