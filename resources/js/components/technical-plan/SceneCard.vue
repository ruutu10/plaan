<script setup lang="ts">
import { Copy, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import type { Scene } from '@/types/technicalPlan';
import { discardAttachment } from './attachments';
import Diamond from './Diamond.vue';
import DragHandle from './DragHandle.vue';
import { isIntermission } from './intermissions';
import { collapseScenes, nextSceneId } from './plan';
import { usePlan } from './planKey';
import R10Input from './R10Input.vue';
import R10Textarea from './R10Textarea.vue';
import SceneCues from './SceneCues.vue';
import { soundFileStillUsed } from './sceneSounds';

/**
 * One scene of the running order: a heading that opens and closes it, and
 * the light, sound and notes the technician is asked for. Dragged by its grip,
 * which the running order around it listens to.
 *
 * The scene is looked up on the injected plan by its id rather than handed in,
 * the way `SceneSoundDialog` does it: the card writes to the scene, and the
 * plan is where that write belongs.
 */
const props = defineProps<{
    sceneId: string;
    /** The scene's number, counted over scenes only, intervals left out. */
    num: number;
    /** Whether this is the scene being dragged right now. */
    dragging: boolean;
}>();

defineEmits<{ startDrag: []; endDrag: [] }>();

const plan = usePlan();

const scene = computed<Scene | undefined>(() =>
    plan.scenes.find((candidate) => candidate.id === props.sceneId),
);

const LIGHT_PRESETS = [
    'kiire blackout',
    'üldvalgus',
    'spot valgus lava keskel',
    'väga hämar sinine valgus',
    'fadeout 1s',
];

function appendLight(text: string): void {
    const current = scene.value;

    if (current) {
        current.light = current.light.trim()
            ? `${current.light.trimEnd()}\n${text}`
            : text;
    }
}

function toggle(): void {
    if (scene.value) {
        scene.value.collapsed = !scene.value.collapsed;
    }
}

function duplicate(): void {
    const current = scene.value;

    if (!current) {
        return;
    }

    const index = plan.scenes.indexOf(current);

    const copy: Scene = {
        ...current,
        id: nextSceneId(plan.scenes),
        // The copy keeps the cues, naming the very same stored files. Two
        // scenes sharing one sound is ordinary now — it is what the "pick one
        // you already have" step does — so a duplicated scene need not lose
        // its music. Copied out of the source's array rather than sharing it,
        // or editing one scene's cues would edit the other's.
        sounds: current.sounds.map((sound) => ({
            ...sound,
            file: sound.file ? { ...sound.file } : null,
        })),
        collapsed: false,
    };
    collapseScenes(plan.scenes);
    plan.scenes.splice(index + 1, 0, copy);
}

/**
 * Whether the scene may be deleted: only while it is not the last one the plan
 * has, because a plan describing no scene at all is not a plan the server
 * takes.
 */
function canRemove(): boolean {
    return plan.scenes.filter((entry) => !isIntermission(entry)).length > 1;
}

async function remove(): Promise<void> {
    const current = scene.value;

    if (!current || !canRemove()) {
        return;
    }

    const [removed] = plan.scenes.splice(plan.scenes.indexOf(current), 1);

    // After the splice, so a file another scene still names survives.
    await Promise.all(
        (removed?.sounds ?? [])
            .map((sound) => sound.file?.id)
            .filter((id) => id && !soundFileStillUsed(plan, id))
            .map((id) => discardAttachment(id as string)),
    );
}
</script>

<template>
    <div
        v-if="scene"
        :class="[
            'overflow-hidden rounded-[14px] border border-r10-grey-200 bg-r10-grey-100 transition-opacity',
            dragging ? 'opacity-40' : '',
        ]"
    >
        <div
            class="flex items-center gap-2 bg-r10-navy px-3 py-3 text-white sm:gap-3 sm:px-4"
        >
            <DragHandle
                size="md"
                class="text-r10-navy-300"
                @dragstart="$emit('startDrag')"
                @dragend="$emit('endDrag')"
            />
            <button
                type="button"
                title="Ava/sulge"
                class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 border-none bg-transparent p-0 text-left text-white sm:gap-2.5"
                @click="toggle"
            >
                <svg
                    width="12"
                    height="12"
                    viewBox="0 0 12 12"
                    fill="none"
                    class="shrink-0 transition-transform"
                    :style="{
                        transform: scene.collapsed ? 'rotate(-90deg)' : 'none',
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
                    {{ scene.name?.trim() ? scene.name : 'Nimeta stseen' }}
                </span>
            </button>
            <div class="ml-auto flex shrink-0 gap-2">
                <button
                    type="button"
                    title="Kopeeri"
                    aria-label="Kopeeri"
                    class="flex cursor-pointer items-center justify-center rounded-full border border-white/20 bg-white/10 p-1.5 text-white transition hover:border-r10-orange hover:text-r10-orange"
                    @click="duplicate"
                >
                    <Copy class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    title="Kustuta"
                    aria-label="Kustuta"
                    :class="[
                        'flex items-center justify-center rounded-full border border-white/20 bg-white/10 p-1.5 text-white transition',
                        canRemove()
                            ? 'cursor-pointer hover:border-r10-error hover:text-r10-error'
                            : 'pointer-events-none opacity-35',
                    ]"
                    @click="remove"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div v-if="!scene.collapsed" class="flex flex-col gap-4 bg-white p-4">
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
                            class="cursor-pointer rounded-full border border-r10-grey-200 bg-r10-grey-100 px-3 py-1 font-r10-body text-[11px] font-bold tracking-[0.03em] text-r10-navy transition hover:border-r10-orange hover:text-r10-orange"
                            @click="appendLight(preset)"
                        >
                            + {{ preset }}
                        </button>
                    </div>
                </div>
                <SceneCues :scene-id="scene.id" />
                <R10Textarea
                    v-model="scene.notes"
                    label="Märkmed"
                    placeholder="Muu oluline…"
                />
                <p class="gap-1 text-xs text-r10-grey-500">
                    Kas on selge, mis lõpetab selle stseeni ja alustab järgmist?
                    Kui ei, siis täpsusta märkmete väljas.
                </p>
            </div>
        </div>
    </div>
</template>
