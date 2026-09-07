<script setup lang="ts">
import { Copy, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Scene } from '@/types/technicalPlan';
import { discardAttachment } from '../attachments';
import Diamond from '../Diamond.vue';
import {
    blankIntermission,
    blankScene,
    collapseScenes,
    intermissionMinutes,
    isIntermission,
    nextSceneId,
    soundFileStillUsed,
    SOUND_PRESETS,
} from '../plan';
import { usePlan, useWizardConfig } from '../planKey';
import R10FileChip from '../R10FileChip.vue';
import R10FormDialog from '../R10FormDialog.vue';
import R10Input from '../R10Input.vue';
import R10Textarea from '../R10Textarea.vue';
import SceneSoundDialog from '../SceneSoundDialog.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();
const config = useWizardConfig();

// The step is mounted afresh every time it is stepped into, so arriving here
// always starts from the closed overview of the scenes rather than from
// whatever was left open last time.
collapseScenes(plan.scenes);

const dragId = ref<string | null>(null);

/* ---- A scene's cues -------------------------------------------------- */

/** The id of the scene whose "add a sound" dialog is open, if any. */
const addingTo = ref<string | null>(null);

function openSoundDialog(scene: Scene): void {
    addingTo.value = scene.id;
}

const soundDialogOpen = computed({
    get: () => addingTo.value !== null,
    set: (open: boolean) => {
        if (!open) {
            addingTo.value = null;
        }
    },
});

function canAddSound(scene: Scene): boolean {
    return scene.sounds.length < config.maxSoundsPerScene;
}

/** Drop one cue from a scene, taking its staged upload with it if it can. */
async function removeSound(scene: Scene, index: number): Promise<void> {
    const [removed] = scene.sounds.splice(index, 1);
    const id = removed?.file?.id;

    // After the splice, so a file another scene still names survives.
    if (id && !soundFileStillUsed(plan, id)) {
        await discardAttachment(id);
    }
}

/**
 * Reorder the cues within a scene. Separate from the scene drag above it and
 * deliberately stopped from bubbling: the scene card is itself a drop target,
 * so a cue dropped without this would reorder the scenes as well.
 */
const dragSound = ref<{ scene: string; id: string } | null>(null);

function onSoundDrop(scene: Scene, targetId: string): void {
    const dragged = dragSound.value;
    dragSound.value = null;

    // A cue only ever moves within its own scene.
    if (!dragged || dragged.scene !== scene.id) {
        return;
    }

    const from = scene.sounds.findIndex((sound) => sound.id === dragged.id);
    const to = scene.sounds.findIndex((sound) => sound.id === targetId);

    if (from < 0 || to < 0 || from === to) {
        return;
    }

    const [moved] = scene.sounds.splice(from, 1);
    scene.sounds.splice(to, 0, moved);
}

const LIGHT_PRESETS = [
    'kiire blackout',
    'üldvalgus',
    'spot valgus lava keskel',
    'väga hämar sinine valgus',
    'fadeout 1s',
];

function appendLight(scene: Scene, text: string): void {
    scene.light = scene.light.trim()
        ? `${scene.light.trimEnd()}\n${text}`
        : text;
}

function appendSound(scene: Scene, text: string): void {
    scene.sound = scene.sound.trim()
        ? `${scene.sound.trimEnd()}\n${text}`
        : text;
}

function addScene(): void {
    collapseScenes(plan.scenes);
    plan.scenes.push(blankScene(nextSceneId(plan.scenes)));
}

function toggle(scene: Scene): void {
    scene.collapsed = !scene.collapsed;
}

function duplicate(scene: Scene): void {
    const index = plan.scenes.indexOf(scene);

    const copy: Scene = {
        ...plan.scenes[index],
        id: nextSceneId(plan.scenes),
        // The copy keeps the cues, naming the very same stored files. Two
        // scenes sharing one sound is ordinary now — it is what the "pick one
        // you already have" step does — so a duplicated scene need not lose
        // its music. Copied out of the source's array rather than sharing it,
        // or editing one scene's cues would edit the other's.
        sounds: plan.scenes[index].sounds.map((sound) => ({
            ...sound,
            file: sound.file ? { ...sound.file } : null,
        })),
        collapsed: false,
    };
    collapseScenes(plan.scenes);
    plan.scenes.splice(index + 1, 0, copy);
}

/**
 * Whether this entry may be dropped. An interval always may; a scene only
 * while it is not the last one the plan has, because a plan describing no
 * scene at all is not a plan the server takes.
 */
function canRemove(scene: Scene): boolean {
    return (
        isIntermission(scene) ||
        plan.scenes.filter((entry) => !isIntermission(entry)).length > 1
    );
}

async function remove(scene: Scene): Promise<void> {
    if (canRemove(scene)) {
        const [removed] = plan.scenes.splice(plan.scenes.indexOf(scene), 1);

        // After the splice, so a file another scene still names survives.
        await Promise.all(
            (removed?.sounds ?? [])
                .map((sound) => sound.file?.id)
                .filter((id) => id && !soundFileStillUsed(plan, id))
                .map((id) => discardAttachment(id as string)),
        );
    }
}

function onDrop(targetId: string): void {
    const from = plan.scenes.findIndex((s) => s.id === dragId.value);
    const to = plan.scenes.findIndex((s) => s.id === targetId);

    if (from < 0 || to < 0 || from === to) {
        dragId.value = null;

        return;
    }

    const [moved] = plan.scenes.splice(from, 1);
    plan.scenes.splice(to, 0, moved);
    dragId.value = null;
}

/* ---- Intervals ------------------------------------------------------- */

/**
 * The running order as the step draws it: the scenes gathered into the halves
 * of the show, with each interval standing between two of them. An interval is
 * an ordinary entry of `plan.scenes` — dragging, deleting and saving all go
 * through the flat array as before — so this only decides where the grouping
 * lines fall.
 */
interface Act {
    /** Which half of the show this is, counted from one. */
    num: number;
    scenes: { scene: Scene; num: number }[];
}

type Block =
    | { kind: 'act'; key: string; act: Act }
    | { kind: 'intermission'; key: string; scene: Scene; minutes: number };

const blocks = computed<Block[]>(() => {
    const built: Block[] = [];
    let scenes: Act['scenes'] = [];
    let num = 0;

    // An interval at the very top or bottom leaves a half with nothing in it;
    // those are left out rather than drawn as an empty heading, so the halves
    // are numbered by what the reader can actually see.
    const closeAct = (): void => {
        if (scenes.length === 0) {
            return;
        }

        built.push({
            kind: 'act',
            key: `vaatus-${built.length}`,
            act: { num: 0, scenes },
        });
        scenes = [];
    };

    plan.scenes.forEach((scene) => {
        const minutes = intermissionMinutes(scene);

        if (minutes > 0) {
            closeAct();
            built.push({ kind: 'intermission', key: scene.id, scene, minutes });

            return;
        }

        num += 1;
        scenes.push({ scene, num });
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
});

/** Whether the show is played in halves at all — nothing is grouped until it is. */
const hasIntermission = computed(() => plan.scenes.some(isIntermission));

/** The interval being written, or null when the dialog is closed. */
const editing = ref<Scene | null>(null);

const minutesInput = ref('15');

const intermissionDialogOpen = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) {
            editing.value = null;
        }
    },
});

const minutesError = ref('');

/**
 * Open the dialog on a fresh interval — held aside rather than pushed onto the
 * plan, so cancelling leaves the running order untouched.
 */
function addIntermission(): void {
    editing.value = blankIntermission(nextSceneId(plan.scenes), 15);
    minutesInput.value = '15';
    minutesError.value = '';
}

function editIntermission(scene: Scene): void {
    editing.value = scene;
    minutesInput.value = String(intermissionMinutes(scene));
    minutesError.value = '';
}

/**
 * Take the length down, holding it to the same range the server does so the
 * performer is stopped here rather than at the save.
 */
function saveIntermission(): void {
    const scene = editing.value;

    if (!scene) {
        return;
    }

    const minutes = Math.floor(Number(minutesInput.value.trim()));

    if (
        !Number.isFinite(minutes) ||
        minutes < 1 ||
        minutes > config.maxIntermissionMinutes
    ) {
        minutesError.value = `Vaheaeg peab kestma 1–${config.maxIntermissionMinutes} minutit.`;

        return;
    }

    scene.intermission = minutes;

    // A new interval joins the running order at the end, where "lisa stseen"
    // puts a scene too; from there it is dragged into place.
    if (!plan.scenes.includes(scene)) {
        plan.scenes.push(scene);
    }

    editing.value = null;
}
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 4 / 7 · Stseenid"
            title="Stseenid"
            lead="Stseen on etenduse loogiline või tehniline osa, kus kasutatud heli- või valguslahendus muutub. Kirjelda siin kõik erinevad valgus- ja helilahendused, mida sinu etendus vajab."
        />

        <div class="flex flex-col gap-[18px]">
            <template v-for="block in blocks" :key="block.key">
                <!-- One half of the show: its scenes are tied together by a
                     heading and a rail down their left. Neither is drawn until
                     the plan has an interval — with nothing to separate, there
                     is nothing to group. -->
                <div
                    v-if="block.kind === 'act'"
                    class="flex flex-col gap-[18px]"
                >
                    <div
                        v-if="hasIntermission"
                        class="flex items-center gap-2.5"
                    >
                        <Diamond :size="9" />
                        <span
                            class="shrink-0 font-r10-display text-xs font-semibold tracking-[0.16em] text-r10-navy uppercase"
                        >
                            {{ block.act.num }}. vaatus
                        </span>
                        <span
                            class="h-px flex-1 bg-r10-grey-200"
                            aria-hidden="true"
                        ></span>
                    </div>

                    <div
                        :class="[
                            'flex flex-col gap-[18px]',
                            hasIntermission
                                ? 'border-l-2 border-r10-navy-300 pl-3 sm:pl-4'
                                : '',
                        ]"
                    >
                        <div
                            v-for="{ scene, num } in block.act.scenes"
                            :key="scene.id"
                            class="overflow-hidden rounded-[14px] border border-r10-grey-200 bg-r10-grey-100"
                            @dragover.prevent
                            @drop.prevent="onDrop(scene.id)"
                        >
                            <div
                                class="flex items-center gap-2 bg-r10-navy px-3 py-3 text-white sm:gap-3 sm:px-4"
                            >
                                <span
                                    draggable="true"
                                    title="Lohista ümberjärjestamiseks"
                                    class="flex shrink-0 cursor-grab text-r10-navy-300"
                                    @dragstart="dragId = scene.id"
                                >
                                    <svg
                                        width="12"
                                        height="18"
                                        viewBox="0 0 12 18"
                                        fill="currentColor"
                                    >
                                        <circle cx="3" cy="3" r="1.5" />
                                        <circle cx="9" cy="3" r="1.5" />
                                        <circle cx="3" cy="9" r="1.5" />
                                        <circle cx="9" cy="9" r="1.5" />
                                        <circle cx="3" cy="15" r="1.5" />
                                        <circle cx="9" cy="15" r="1.5" />
                                    </svg>
                                </span>
                                <button
                                    type="button"
                                    title="Ava/sulge"
                                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 border-none bg-transparent p-0 text-left text-white sm:gap-2.5"
                                    @click="toggle(scene)"
                                >
                                    <svg
                                        width="12"
                                        height="12"
                                        viewBox="0 0 12 12"
                                        fill="none"
                                        class="shrink-0 transition-transform"
                                        :style="{
                                            transform: scene.collapsed
                                                ? 'rotate(-90deg)'
                                                : 'none',
                                        }"
                                    >
                                        <path
                                            d="M2.5 4.5L6 8l3.5-3.5"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <Diamond :size="9" />
                                    <span
                                        class="shrink-0 font-r10-display text-sm font-semibold tracking-[0.03em] uppercase"
                                    >
                                        Stseen {{ num }}
                                    </span>
                                    <span
                                        v-if="scene.collapsed"
                                        class="min-w-0 overflow-hidden text-[13px] text-ellipsis whitespace-nowrap text-r10-navy-200"
                                    >
                                        {{
                                            scene.name?.trim()
                                                ? scene.name
                                                : 'Nimeta stseen'
                                        }}
                                    </span>
                                </button>
                                <div class="ml-auto flex shrink-0 gap-2">
                                    <button
                                        type="button"
                                        title="Kopeeri"
                                        aria-label="Kopeeri"
                                        class="flex cursor-pointer items-center justify-center rounded-full border border-white/20 bg-white/10 p-1.5 text-white transition hover:border-r10-orange hover:text-r10-orange"
                                        @click="duplicate(scene)"
                                    >
                                        <Copy class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        title="Kustuta"
                                        aria-label="Kustuta"
                                        :class="[
                                            'flex items-center justify-center rounded-full border border-white/20 bg-white/10 p-1.5 text-white transition',
                                            canRemove(scene)
                                                ? 'cursor-pointer hover:border-r10-error hover:text-r10-error'
                                                : 'pointer-events-none opacity-35',
                                        ]"
                                        @click="remove(scene)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="!scene.collapsed"
                                class="flex flex-col gap-4 bg-white p-4"
                            >
                                <R10Input
                                    v-model="scene.name"
                                    label="Nimi"
                                    placeholder="Nt 'lavale tulek' või 'järgmise mängu tutvustus'"
                                />
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="flex flex-col gap-2">
                                        <R10Textarea
                                            v-model="scene.light"
                                            label="Valgus"
                                            placeholder="Valguse soovid, nt 'Spot lava keskele' või 'punane hämar valgus üle kogu lava'"
                                        />
                                        <div class="flex flex-wrap gap-1.5">
                                            <button
                                                v-for="preset in LIGHT_PRESETS"
                                                :key="preset"
                                                type="button"
                                                :class="[
                                                    'cursor-pointer rounded-full border border-r10-grey-200 bg-r10-grey-100 px-3 py-1 font-r10-body text-[11px] font-bold tracking-[0.03em] text-r10-navy transition hover:border-r10-orange hover:text-r10-orange',
                                                ]"
                                                @click="
                                                    appendLight(scene, preset)
                                                "
                                            >
                                                + {{ preset }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <span
                                            class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                                        >
                                            Heli
                                        </span>
                                        <ul
                                            class="-mt-0.5 flex list-disc flex-col gap-1 pl-4 text-xs text-r10-grey-500"
                                        >
                                            <li>
                                                Iga muusikapala peab olema
                                                <strong class="font-semibold"
                                                    >konkreetne</strong
                                                >
                                                (pealkiri + esitaja) või
                                                teadlikult "juhuslik" valik (nt
                                                "vabalt valitud kurb
                                                instrumentaalne klaveripala").
                                            </li>
                                            <li>
                                                Kui alguskoht vajab täpsust,
                                                lisa juurde
                                                <strong class="font-semibold"
                                                    >algusaeg/sisenemispunkt</strong
                                                >
                                                (nt „0:10 alates“), vajadusel
                                                valjus ja
                                                <strong class="font-semibold"
                                                    >kuidas
                                                    mängida/lõpetada</strong
                                                >
                                                (fade, järsk katkestus).
                                            </li>
                                        </ul>
                                        <!-- The scene's cues, in the order they are
                                 played. Each row drags within its own scene;
                                 the drop is stopped from bubbling because the
                                 scene card around it is a drop target too. -->
                                        <div
                                            v-for="(
                                                sound, position
                                            ) in scene.sounds"
                                            :key="sound.id"
                                            class="flex items-center gap-2"
                                            @dragover.prevent
                                            @drop.stop.prevent="
                                                onSoundDrop(scene, sound.id)
                                            "
                                        >
                                            <span
                                                draggable="true"
                                                title="Lohista ümberjärjestamiseks"
                                                class="flex shrink-0 cursor-grab text-r10-grey-500"
                                                @dragstart.stop="
                                                    dragSound = {
                                                        scene: scene.id,
                                                        id: sound.id,
                                                    }
                                                "
                                            >
                                                <svg
                                                    width="10"
                                                    height="16"
                                                    viewBox="0 0 12 18"
                                                    fill="currentColor"
                                                >
                                                    <circle
                                                        cx="3"
                                                        cy="3"
                                                        r="1.5"
                                                    />
                                                    <circle
                                                        cx="9"
                                                        cy="3"
                                                        r="1.5"
                                                    />
                                                    <circle
                                                        cx="3"
                                                        cy="9"
                                                        r="1.5"
                                                    />
                                                    <circle
                                                        cx="9"
                                                        cy="9"
                                                        r="1.5"
                                                    />
                                                    <circle
                                                        cx="3"
                                                        cy="15"
                                                        r="1.5"
                                                    />
                                                    <circle
                                                        cx="9"
                                                        cy="15"
                                                        r="1.5"
                                                    />
                                                </svg>
                                            </span>

                                            <R10FileChip
                                                v-if="sound.file"
                                                class="min-w-0 flex-1"
                                                :file="sound.file"
                                                open-label="Ava uues aknas"
                                                @remove="
                                                    removeSound(scene, position)
                                                "
                                            />
                                            <span
                                                v-else
                                                class="flex min-w-0 flex-1 items-center gap-3 rounded-[10px] border border-r10-grey-200 bg-white px-3.5 py-2.5"
                                            >
                                                <Diamond :size="8" />
                                                <a
                                                    :href="sound.url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="min-w-0 flex-1 truncate text-sm font-medium text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange"
                                                >
                                                    {{ sound.url }}
                                                </a>
                                                <button
                                                    type="button"
                                                    title="Eemalda"
                                                    class="shrink-0 cursor-pointer border-none bg-transparent text-[15px] leading-none text-r10-grey-500 transition hover:text-r10-error"
                                                    @click="
                                                        removeSound(
                                                            scene,
                                                            position,
                                                        )
                                                    "
                                                >
                                                    ✕
                                                </button>
                                            </span>
                                        </div>

                                        <button
                                            v-if="canAddSound(scene)"
                                            type="button"
                                            class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-full border-2 border-r10-navy-300 bg-transparent px-3.5 py-2 font-r10-body text-[11px] font-bold tracking-[0.06em] text-r10-navy uppercase transition hover:border-r10-orange hover:text-r10-orange"
                                            @click="openSoundDialog(scene)"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                            Lisa helifail
                                        </button>
                                        <p
                                            v-else
                                            class="text-xs text-r10-grey-500"
                                        >
                                            Ühel stseenil saab olla kuni
                                            {{ config.maxSoundsPerScene }} heli.
                                        </p>
                                        <textarea
                                            v-model="scene.sound"
                                            placeholder="Heli kasutuse kirjeldus, nt „alusta 10. sekundist, pane mängima siis kui esinejad tarduvad“"
                                            class="min-h-[56px] w-full resize-y rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-r10-body text-sm leading-normal text-r10-ink outline-none focus:border-r10-orange"
                                        ></textarea>
                                        <div class="flex flex-wrap gap-1.5">
                                            <button
                                                v-for="preset in SOUND_PRESETS"
                                                :key="preset"
                                                type="button"
                                                :class="[
                                                    'cursor-pointer rounded-full border border-r10-grey-200 bg-r10-grey-100 px-3 py-1 font-r10-body text-[11px] font-bold tracking-[0.03em] text-r10-navy transition hover:border-r10-orange hover:text-r10-orange',
                                                ]"
                                                @click="
                                                    appendSound(scene, preset)
                                                "
                                            >
                                                + {{ preset }}
                                            </button>
                                        </div>
                                    </div>
                                    <R10Textarea
                                        v-model="scene.notes"
                                        label="Märkmed"
                                        placeholder="Muu oluline…"
                                    />
                                    <p class="gap-1 text-xs text-r10-grey-500">
                                        Kas on selge, mis lõpetab selle stseeni
                                        ja alustab järgmist? Kui ei, siis
                                        täpsusta märkmete väljas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- The interval: a rule across the running order rather than a
                     card, because nothing about it is played. It drags and
                     deletes like a scene, and its length is opened by
                     clicking it. -->
                <div
                    v-else
                    class="flex items-center gap-2.5"
                    @dragover.prevent
                    @drop.prevent="onDrop(block.scene.id)"
                >
                    <span
                        class="h-0.5 flex-1 rounded-full bg-r10-orange/35"
                        aria-hidden="true"
                    ></span>
                    <span
                        draggable="true"
                        title="Lohista ümberjärjestamiseks"
                        class="flex shrink-0 cursor-grab text-r10-grey-500"
                        @dragstart="dragId = block.scene.id"
                    >
                        <svg
                            width="10"
                            height="16"
                            viewBox="0 0 12 18"
                            fill="currentColor"
                        >
                            <circle cx="3" cy="3" r="1.5" />
                            <circle cx="9" cy="3" r="1.5" />
                            <circle cx="3" cy="9" r="1.5" />
                            <circle cx="9" cy="9" r="1.5" />
                            <circle cx="3" cy="15" r="1.5" />
                            <circle cx="9" cy="15" r="1.5" />
                        </svg>
                    </span>
                    <button
                        type="button"
                        title="Muuda vaheaja pikkust"
                        class="flex shrink-0 cursor-pointer items-center gap-2 rounded-full border-2 border-dashed border-r10-orange bg-white px-4 py-1.5 font-r10-body text-[11px] font-bold tracking-[0.06em] text-r10-navy uppercase transition hover:border-r10-orange-700 hover:text-r10-orange"
                        @click="editIntermission(block.scene)"
                    >
                        <Diamond :size="8" />
                        Vaheaeg · {{ block.minutes }} min
                    </button>
                    <button
                        type="button"
                        title="Kustuta vaheaeg"
                        aria-label="Kustuta vaheaeg"
                        class="flex shrink-0 cursor-pointer items-center justify-center rounded-full border border-r10-grey-200 bg-white p-1.5 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                        @click="remove(block.scene)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                    <span
                        class="h-0.5 flex-1 rounded-full bg-r10-orange/35"
                        aria-hidden="true"
                    ></span>
                </div>
            </template>
        </div>

        <div class="mt-[18px] flex flex-wrap gap-2.5">
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-2.5 rounded-full border-2 border-dashed border-r10-navy-300 bg-white px-[22px] py-2.5 font-r10-body text-[13px] font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:border-r10-orange hover:text-r10-orange"
                @click="addScene"
            >
                <span class="text-lg leading-none">+</span> Lisa stseen
            </button>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-2.5 rounded-full border-2 border-dashed border-r10-navy-300 bg-white px-[22px] py-2.5 font-r10-body text-[13px] font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:border-r10-orange hover:text-r10-orange"
                @click="addIntermission"
            >
                <span class="text-lg leading-none">+</span> Lisa vaheaeg
            </button>
        </div>

        <!-- One dialog for the whole step, pointed at whichever scene asked
             for it, so a scene card carries no modal of its own. -->
        <SceneSoundDialog
            v-if="addingTo"
            v-model:open="soundDialogOpen"
            :scene-id="addingTo"
        />

        <R10FormDialog
            v-model:open="intermissionDialogOpen"
            title="Vaheaeg"
            description="Kui pikk on vaheaeg? Vaheaeg jagab etenduse kaheks pooleks; lisatud vaheaja saad lohistades õigesse kohta tõsta."
            submit-label="Salvesta"
            test-id-prefix="intermission"
            @submit="saveIntermission"
        >
            <R10Input
                v-model="minutesInput"
                type="number"
                min="1"
                :max="config.maxIntermissionMinutes"
                label="Pikkus minutites"
                placeholder="15"
                :error="minutesError"
                error-test-id="intermission-minutes-error"
            />
        </R10FormDialog>
    </section>
</template>
