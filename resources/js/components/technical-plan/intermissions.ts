import type { Scene, WizardConfig } from '@/types/technicalPlan';
import { blankScene } from './plan';

/**
 * The break between two halves of the show. It is an entry in the scenes array
 * like any other — that is what keeps it in its place in the running order —
 * carrying nothing but how long it lasts.
 */
export function blankIntermission(
    id: string,
    minutes: number,
    act: number | null = null,
): Scene {
    return {
        ...blankScene(id),
        intermission: minutes,
        actMinutes: act,
        collapsed: true,
    };
}

/**
 * How long an entry's interval lasts, in whole minutes, or zero when the entry
 * is an ordinary scene. The one place the stored value is judged, so the
 * wizard, the document and the technician's view all draw the line in the same
 * place; `App\Http\Resources\TechnicalPlan::intermission()` mirrors it
 * server-side.
 */
export function intermissionMinutes(
    scene: Pick<Scene, 'intermission'>,
): number {
    const minutes = Math.floor(Number(scene.intermission ?? 0));

    return Number.isFinite(minutes) && minutes > 0 ? minutes : 0;
}

/** Whether this entry is an interval rather than a scene. */
export function isIntermission(scene: Pick<Scene, 'intermission'>): boolean {
    return intermissionMinutes(scene) > 0;
}

/** The interval as every reader is shown it, minutes included. */
export function intermissionLabel(minutes: number): string {
    return `Vaheaeg — ${minutes} min`;
}

/**
 * How long the part of the show an interval *ends* runs, as its author wrote
 * it, or null when nobody has said. Only an interval carries this; see
 * {@link showParts}, which is where it is turned into the split the reader
 * sees. `App\Http\Resources\TechnicalPlan::actMinutes()` mirrors it.
 */
export function actMinutes(scene: Pick<Scene, 'actMinutes'>): number | null {
    const minutes = Math.floor(Number(scene.actMinutes ?? 0));

    return Number.isFinite(minutes) && minutes > 0 ? minutes : null;
}

/**
 * Read a length the performer typed, holding it to the same range the server
 * does. Null when what they wrote is not a length at all — blank included.
 */
function readMinutes(typed: string, max: number): number | null {
    const minutes = Math.floor(Number(typed.trim()));

    return Number.isFinite(minutes) && minutes >= 1 && minutes <= max
        ? minutes
        : null;
}

/** The interval dialog's two lengths, read off what was typed. */
export interface IntermissionForm {
    /** How long the interval lasts, or null when it was not given as a length. */
    minutes: number | null;
    /** How long the part it ends runs, or null on the same terms. */
    act: number | null;
    errors: { minutes: string; act: string };
}

/**
 * Read the interval dialog's answers, so the performer is stopped where they
 * typed rather than at the save.
 *
 * Both lengths are required. The length of the part an interval ends is the one
 * thing no reader can work out afterwards — see {@link showParts}, where the
 * closing part is whatever is left of the evening — so an interval that does not
 * carry it leaves the technician with no idea how long either half runs.
 */
export function readIntermissionForm(
    minutesTyped: string,
    actTyped: string,
    config: Pick<WizardConfig, 'maxIntermissionMinutes' | 'maxActMinutes'>,
): IntermissionForm {
    const minutes = readMinutes(minutesTyped, config.maxIntermissionMinutes);
    const act = readMinutes(actTyped, config.maxActMinutes);

    return {
        minutes,
        act,
        errors: {
            minutes:
                minutes === null
                    ? `Vaheaeg peab kestma 1–${config.maxIntermissionMinutes} minutit.`
                    : '',
            act:
                act === null
                    ? `Märgi eelneva osa pikkus: 1–${config.maxActMinutes} minutit.`
                    : '',
        },
    };
}

/** One part of a show played in parts — see {@link showParts}. */
export interface ShowPart {
    /** Which part this is, counted from one over the parts a reader sees. */
    num: number;
    /** Where the part opens: the index in `scenes` of its first scene. */
    start: number;
    /** How many minutes it runs, or null when it cannot be known. */
    minutes: number | null;
}

/**
 * How long each part of a show played in parts runs, so the technician can see
 * the evening's shape at a glance rather than working it out at the desk.
 *
 * A part is a run of scenes with an interval beside it. Each interval says how
 * long the part it ends runs; the closing part is not asked, because it is what
 * is left of the evening — the show's own running time, less every interval and
 * every part already named. That subtraction only holds when nothing is
 * missing, so a part whose length cannot be worked out is reported as null
 * rather than guessed at.
 *
 * A show with nothing to split — no interval, or one standing before the first
 * scene or after the last — has no parts at all, and no reader shows a split
 * for it. Mirrored by `App\Http\Resources\TechnicalPlan::showParts()`.
 */
export function showParts(
    scenes: Scene[],
    totalMinutes: number | null,
): ShowPart[] {
    const parts: { start: number; minutes: number | null }[] = [];
    let breaks = 0;
    let open: number | null = null;

    scenes.forEach((scene, index) => {
        const interval = intermissionMinutes(scene);

        if (interval > 0) {
            breaks += interval;

            // An interval with no scenes behind it closes nothing: it stands
            // at the top of the running order, or straight after another.
            if (open !== null) {
                parts.push({ start: open, minutes: actMinutes(scene) });
                open = null;
            }

            return;
        }

        if (open === null) {
            open = index;
        }
    });

    // Whatever the last interval left open closes the show.
    if (open !== null) {
        parts.push({ start: open, minutes: null });
    }

    if (parts.length < 2) {
        return [];
    }

    const named = parts.filter((part) => part.minutes !== null);
    const total = totalMinutes ?? 0;
    const closing = parts[parts.length - 1];

    // The closing part is the only one worth working out, and only while it is
    // the only one left unsaid.
    if (
        closing.minutes === null &&
        total > 0 &&
        named.length === parts.length - 1
    ) {
        const rest =
            total -
            breaks -
            named.reduce((sum, part) => sum + (part.minutes ?? 0), 0);

        closing.minutes = rest > 0 ? rest : null;
    }

    return parts.map((part, index) => ({ num: index + 1, ...part }));
}

/** One part of the show as every reader is shown it, its length included. */
export function actLabel(num: number, minutes: number | null): string {
    return minutes === null
        ? `${num}. vaatus`
        : `${num}. vaatus — ${minutes} min`;
}

/**
 * One half of the show as the scenes step draws it — see {@link buildBlocks}.
 */
export interface Act {
    /** Which half of the show this is, counted from one. */
    num: number;
    /** How long it runs, as it was named or worked out; null when neither. */
    minutes: number | null;
    /** Each scene with its number and its place in the running order. */
    scenes: { scene: Scene; num: number; index: number }[];
}

export type Block =
    | { kind: 'act'; key: string; act: Act }
    | {
          kind: 'intermission';
          key: string;
          scene: Scene;
          minutes: number;
          index: number;
      };

/**
 * The running order as the scenes step draws it: the scenes gathered into the
 * halves of the show, with each interval standing between two of them. An
 * interval is an ordinary entry of `scenes` — dragging, deleting and saving
 * all go through the flat array — so this only decides where the grouping
 * lines fall.
 *
 * Each half's length comes from {@link showParts}, by the same rules the
 * document and the mail use, so the split the performer sees here is the one
 * the technician gets.
 */
export function buildBlocks(
    scenes: Scene[],
    totalMinutes: number | null,
): Block[] {
    const parts = new Map(
        showParts(scenes, totalMinutes).map((part) => [part.start, part]),
    );
    const built: Block[] = [];
    let open: Act['scenes'] = [];
    let opensAt = 0;
    let num = 0;

    // An interval at the very top or bottom leaves a half with nothing in it;
    // those are left out rather than drawn as an empty heading, so the halves
    // are numbered by what the reader can actually see.
    const closeAct = (): void => {
        if (open.length === 0) {
            return;
        }

        built.push({
            kind: 'act',
            key: `vaatus-${built.length}`,
            act: {
                num: 0,
                minutes: parts.get(opensAt)?.minutes ?? null,
                scenes: open,
            },
        });
        open = [];
    };

    scenes.forEach((scene, index) => {
        const minutes = intermissionMinutes(scene);

        if (minutes > 0) {
            closeAct();
            built.push({
                kind: 'intermission',
                key: scene.id,
                scene,
                minutes,
                index,
            });

            return;
        }

        if (open.length === 0) {
            opensAt = index;
        }

        num += 1;
        open.push({ scene, num, index });
    });

    closeAct();

    let act = 0;

    built.forEach((block) => {
        if (block.kind === 'act') {
            act += 1;
            block.act.num = act;
        }
    });

    return built;
}
