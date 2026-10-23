import type { Plan, SceneSound, WizardConfig } from '@/types/technicalPlan';
import { isReady } from './files';

/**
 * The one-click sound cues offered under the scene's description. Shared by the
 * scene card and the add-a-sound dialog, which write to the same field.
 */
export const SOUND_PRESETS = [
    'ruutu10 tunnus 3s',
    'ruutu10 tunnus 15s',
    'film noare (vabal valikul)',
    'shakespeare (vabal valikul)',
];

/**
 * Whether a cue points at something real: a file that finished uploading, or a
 * link that was actually typed.
 *
 * The one place this question is answered. It decides what the wizard posts,
 * what it expects back from the save, what the document renders and what
 * survives hydration — and those four have to agree, or a cue is dropped in one
 * place and kept in another.
 */
export function soundHasSource(sound: SceneSound): boolean {
    return isReady(sound.file) || (sound.url ?? '').trim() !== '';
}

/**
 * Whether any cue anywhere in the plan still names this stored file.
 *
 * The same handle may serve several scenes — reusing a sting is the point of
 * the picker — and a *staged* upload is deleted for real rather than swept up
 * later, so letting go of one scene's cue must not take the sound out from
 * under another's. Ask this after the cue has left the plan, so what remains is
 * what is really still wanted.
 */
export function soundFileStillUsed(plan: Plan, id: string): boolean {
    return plan.scenes.some((scene) =>
        scene.sounds.some((sound) => sound.file?.id === id),
    );
}

/**
 * Extensions a browser can decode into a waveform. Wider than the upload
 * allowlist in `config/technical_plan.php`, because a linked file was never
 * ours to constrain — anything the browser plays is worth showing.
 */
const AUDIO_EXTENSIONS = [
    'mp3',
    'wav',
    'ogg',
    'oga',
    'm4a',
    'aac',
    'flac',
    'opus',
    'weba',
    'aif',
    'aiff',
];

/** Whether a file name ends in an extension a browser can decode. */
function hasAudioExtension(name: string): boolean {
    const extension = name.split('.').pop()?.toLowerCase() ?? '';

    return AUDIO_EXTENSIONS.includes(extension);
}

/**
 * Whether a URL points straight at an audio file. Only the path decides: a
 * sharing page (YouTube, Google Drive, …) names no audio file, and a query
 * string that happens to mention one (say `?file=cue.mp3`) is not the resource
 * being fetched. Such links can only be opened, never played.
 */
function isDirectAudioUrl(url: string): boolean {
    try {
        // A relative URL needs *a* base before it will parse, and only the path
        // is read afterwards — so a stand-in base answers as well as the page's
        // own origin would, without this needing a browser to run in.
        return hasAudioExtension(
            new URL(url, 'https://plaan.invalid').pathname,
        );
    } catch {
        return false;
    }
}

/**
 * The URL a cue can actually be played from, or `null` when it is only
 * reachable through a link a player cannot read. An upload is judged by its
 * stored name — the URL that streams it carries no extension — while a link is
 * judged by its path.
 */
export function soundAudioUrl(sound: SceneSound): string | null {
    const file = sound.file?.status === 'ready' ? sound.file : null;

    if (file?.url && hasAudioExtension(file.name)) {
        return file.url;
    }

    const url = sound.url.trim();

    return url && isDirectAudioUrl(url) ? url : null;
}

/**
 * Why a cue's link cannot be used, or `null` when it can.
 *
 * Mirrors the `url:http,https` rule in `StoreTechnicalPlanRequest`, so the
 * wizard refuses a bad link where the performer typed it rather than leaving
 * the save to fail later — and it refuses it for the same reason the server
 * does. The link is rendered as an `href` in the mail, on the printout and in
 * the technician's view, so a `javascript:` or `data:` URL would be somebody
 * else's code running under a reader who only opened a plan.
 *
 * The length limit is the server's own, handed to the wizard in its config
 * rather than written down a second time here.
 */
export function soundLinkError(
    url: string,
    config: WizardConfig,
): string | null {
    const value = url.trim();

    if (value === '') {
        return 'Lisa helifaili link.';
    }

    if (value.length > config.maxSoundUrlLength) {
        return `Link on liiga pikk (max ${config.maxSoundUrlLength} märki).`;
    }

    let parsed: URL;

    try {
        // No base URL on purpose: a cue's link has to stand on its own, so a
        // bare `example.com/lugu.mp3` is as unusable as a relative path.
        parsed = new URL(value);
    } catch {
        return 'Link peab olema täielik aadress, nt https://example.com/muusika.mp3';
    }

    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        return 'Link peab algama http:// või https:// aadressiga.';
    }

    return null;
}
