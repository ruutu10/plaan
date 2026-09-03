import { jsonHeaders } from '@/lib/http';
import type {
    Plan,
    PlanFile,
    ReusableSound,
    WizardConfig,
} from '@/types/technicalPlan';
import { formatFileSize } from './plan';

/**
 * Shared file-upload plumbing for the wizard. Files are uploaded on their own
 * (staged server-side) and only referenced by handle in the plan, so every step
 * that offers an upload — the plan's own attachments, a scene's sound file —
 * talks to the same endpoints in the same way.
 */

function headers(): Record<string, string> {
    return jsonHeaders(true);
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

        if (response.status === 401) {
            return failed(
                'Sessioon on aegunud. Logi uuesti sisse ja proovi uuesti.',
            );
        }

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
 * Stage a copy of a sound file the performer already has on one of their other
 * plans. Answers with the handle of the copy — the plan it came from keeps its
 * own file, so dropping this cue later never reaches back into that plan.
 */
export async function reuseSound(sound: ReusableSound): Promise<PlanFile> {
    const failed = (error: string): PlanFile => ({
        id: '',
        name: sound.name,
        size: sound.size,
        status: 'error',
        error,
    });

    try {
        const response = await fetch(
            `/api/tehnikaplaan/sounds/${encodeURIComponent(sound.id)}/reuse`,
            { method: 'POST', headers: headers() },
        );

        const data = await response.json().catch(() => ({}));

        if (response.status === 401) {
            return failed(
                'Sessioon on aegunud. Logi uuesti sisse ja proovi uuesti.',
            );
        }

        if (!response.ok) {
            return failed(
                (data.message as string) ?? 'Heli lisamine ebaõnnestus.',
            );
        }

        return {
            id: data.id as string,
            name: (data.name as string) ?? sound.name,
            size: (data.size as number) ?? sound.size,
            url: data.url as string,
            downloadUrl: data.downloadUrl as string,
            status: 'ready',
        };
    } catch {
        return failed('Heli lisamine ebaõnnestus.');
    }
}

/**
 * The sound files the performer could reuse, drawn from their other plans. The
 * plan being written is left out — the wizard already holds its own cues and
 * offers them without asking the server.
 */
export async function fetchReusableSounds(
    token: string | null,
): Promise<ReusableSound[]> {
    const query = token ? '?exclude=' + encodeURIComponent(token) : '';

    try {
        const response = await fetch('/api/tehnikaplaan/sounds' + query, {
            headers: headers(),
        });

        if (!response.ok) {
            return [];
        }

        const data = await response.json().catch(() => ({}));

        return (data.results as ReusableSound[]) ?? [];
    } catch {
        return [];
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

/**
 * Drop a cue's file — but only once no cue anywhere in the plan still names it.
 *
 * The same handle may serve several scenes, which is the whole point of reusing
 * a sting, and a *staged* upload is deleted for real rather than swept up
 * later. Letting go of one scene's cue must therefore not take the sound out
 * from under another's. Call this after the cue has left the plan, so what
 * remains is what is really still wanted.
 */
export async function discardSoundFile(
    plan: Plan,
    file: PlanFile | null | undefined,
): Promise<void> {
    const id = file?.id;

    if (!id) {
        return;
    }

    const stillUsed = plan.scenes.some((scene) =>
        scene.sounds.some((sound) => sound.file?.id === id),
    );

    if (stillUsed) {
        return;
    }

    await discardAttachment(id);
}
