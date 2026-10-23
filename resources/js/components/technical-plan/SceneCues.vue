<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useListReorder } from '@/composables/useListReorder';
import type { Scene } from '@/types/technicalPlan';
import { discardAttachment } from './attachments';
import Diamond from './Diamond.vue';
import DragHandle from './DragHandle.vue';
import DropGap from './DropGap.vue';
import { usePlan, useWizardConfig } from './planKey';
import R10FileChip from './R10FileChip.vue';
import SceneSoundDialog from './SceneSoundDialog.vue';
import { soundFileStillUsed, SOUND_PRESETS } from './sceneSounds';

/**
 * A scene's sound: the cues it plays, in order, and the description of how
 * they are played. Looked up on the injected plan by its id, as `SceneCard`
 * is, because this is where the scene's cues are written.
 */
const props = defineProps<{ sceneId: string }>();

const plan = usePlan();
const config = useWizardConfig();

const scene = computed<Scene | undefined>(() =>
    plan.scenes.find((candidate) => candidate.id === props.sceneId),
);

/**
 * Reorder the cues within the scene. A list of its own, so a cue only ever
 * moves within its own scene, and a scene dropped over the cues is left to
 * bubble on to the running order around them.
 */
const {
    dragId: draggedCue,
    startDrag,
    endDrag,
    onDragOver,
    onDragLeave,
    showsGap,
    onDrop,
} = useListReorder(() => scene.value?.sounds ?? []);

/** Whether the "add a sound" dialog is open. Mounted only while it is. */
const adding = ref(false);

function canAddSound(): boolean {
    return (scene.value?.sounds.length ?? 0) < config.maxSoundsPerScene;
}

/** Drop one cue, taking its staged upload with it if it can. */
async function removeSound(index: number): Promise<void> {
    const [removed] = scene.value?.sounds.splice(index, 1) ?? [];
    const id = removed?.file?.id;

    // After the splice, so a file another scene still names survives.
    if (id && !soundFileStillUsed(plan, id)) {
        await discardAttachment(id);
    }
}

function appendSound(text: string): void {
    const current = scene.value;

    if (current) {
        current.sound = current.sound.trim()
            ? `${current.sound.trimEnd()}\n${text}`
            : text;
    }
}
</script>

<template>
    <div v-if="scene" class="flex flex-col gap-1.5">
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
                <strong class="font-semibold">konkreetne</strong>
                (pealkiri + esitaja) või teadlikult "juhuslik" valik (nt "vabalt
                valitud kurb instrumentaalne klaveripala").
            </li>
            <li>
                Kui alguskoht vajab täpsust, lisa juurde
                <strong class="font-semibold">algusaeg/sisenemispunkt</strong>
                (nt „0:10 alates“), vajadusel valjus ja
                <strong class="font-semibold">kuidas mängida/lõpetada</strong>
                (fade, järsk katkestus).
            </li>
        </ul>

        <!-- The cues, in the order they are played. -->
        <div
            v-if="scene.sounds.length"
            class="flex flex-col gap-1.5"
            @dragover.prevent
            @dragleave="onDragLeave"
            @drop.prevent="onDrop"
        >
            <template v-for="(sound, position) in scene.sounds" :key="sound.id">
                <DropGap :open="showsGap(position)" :spacing="6" />
                <div
                    :class="[
                        'flex items-center gap-2 transition-opacity',
                        draggedCue === sound.id ? 'opacity-40' : '',
                    ]"
                    @dragover="onDragOver(position, $event)"
                >
                    <DragHandle
                        class="text-r10-grey-500"
                        @dragstart.stop="startDrag(sound.id)"
                        @dragend.stop="endDrag"
                    />

                    <R10FileChip
                        v-if="sound.file"
                        class="min-w-0 flex-1"
                        :file="sound.file"
                        open-label="Ava uues aknas"
                        @remove="removeSound(position)"
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
                            @click="removeSound(position)"
                        >
                            ✕
                        </button>
                    </span>
                </div>
                <DropGap
                    v-if="position === scene.sounds.length - 1"
                    :open="showsGap(scene.sounds.length)"
                    :spacing="6"
                />
            </template>
        </div>

        <button
            v-if="canAddSound()"
            type="button"
            class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-full border-2 border-r10-navy-300 bg-transparent px-3.5 py-2 font-r10-body text-[11px] font-bold tracking-[0.06em] text-r10-navy uppercase transition hover:border-r10-orange hover:text-r10-orange"
            @click="adding = true"
        >
            <Plus class="h-3.5 w-3.5" />
            Lisa helifail
        </button>
        <p v-else class="text-xs text-r10-grey-500">
            Ühel stseenil saab olla kuni {{ config.maxSoundsPerScene }} heli.
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
                class="cursor-pointer rounded-full border border-r10-grey-200 bg-r10-grey-100 px-3 py-1 font-r10-body text-[11px] font-bold tracking-[0.03em] text-r10-navy transition hover:border-r10-orange hover:text-r10-orange"
                @click="appendSound(preset)"
            >
                + {{ preset }}
            </button>
        </div>

        <SceneSoundDialog
            v-if="adding"
            v-model:open="adding"
            :scene-id="scene.id"
        />
    </div>
</template>
