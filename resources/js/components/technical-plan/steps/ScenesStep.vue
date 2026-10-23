<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useListReorder } from '@/composables/useListReorder';
import type { Scene } from '@/types/technicalPlan';
import Diamond from '../Diamond.vue';
import DragHandle from '../DragHandle.vue';
import DropGap from '../DropGap.vue';
import IntermissionDialog from '../IntermissionDialog.vue';
import {
    actLabel,
    blankIntermission,
    buildBlocks,
    isIntermission,
} from '../intermissions';
import { blankScene, collapseScenes, nextSceneId } from '../plan';
import { usePlan } from '../planKey';
import R10Notice from '../R10Notice.vue';
import SceneCard from '../SceneCard.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();

// The step is mounted afresh every time it is stepped into, so arriving here
// always starts from the closed overview of the scenes rather than from
// whatever was left open last time.
collapseScenes(plan.scenes);

/** Reordering the running order — scenes and intervals alike. */
const {
    dragId,
    startDrag,
    endDrag,
    onDragOver,
    onDragLeave,
    showsGap,
    onDrop,
} = useListReorder(() => plan.scenes);

function addScene(): void {
    collapseScenes(plan.scenes);
    plan.scenes.push(blankScene(nextSceneId(plan.scenes)));
}

/* ---- Intervals ------------------------------------------------------- */

/** The running order grouped into the halves of the show. */
const blocks = computed(() => buildBlocks(plan.scenes, plan.meta.duration));

/** Whether the show is played in halves at all — nothing is grouped until it is. */
const hasIntermission = computed(() => plan.scenes.some(isIntermission));

/** The interval being written, or null when the dialog is closed. */
const editing = ref<Scene | null>(null);

function addIntermission(): void {
    editing.value = blankIntermission(nextSceneId(plan.scenes), 15);
}

/** An interval carries no cues, so nothing has to be cleaned up after it. */
function removeIntermission(scene: Scene): void {
    plan.scenes.splice(plan.scenes.indexOf(scene), 1);
}

/** The show's own running time, which the closing part is worked out from. */
const showMinutes = computed(() => plan.meta.duration ?? 0);

/**
 * Whether the parts named so far already fill the evening, leaving the closing
 * part nothing. Said out loud rather than shown as a missing length, because
 * the performer is the only one who can put it right.
 */
const partsOverrun = computed(() => {
    const drawn = blocks.value.filter((block) => block.kind === 'act');

    return (
        showMinutes.value > 0 &&
        drawn.length > 1 &&
        drawn[drawn.length - 1].act.minutes === null &&
        // Only when every other part was named: a part left unsaid is not an
        // overrun, it is simply unknown.
        drawn.slice(0, -1).every((block) => block.act.minutes !== null)
    );
});
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 4 / 7 · Stseenid"
            title="Stseenid"
            lead="Stseen on etenduse loogiline või tehniline osa, kus kasutatud heli- või valguslahendus muutub. Kirjelda siin kõik erinevad valgus- ja helilahendused, mida sinu etendus vajab."
        />

        <!-- The whole running order takes the drop, so letting go over a gap
             between two cards lands just as well as letting go over a card;
             the cards themselves only work out which gap the pointer is at. -->
        <div
            class="flex flex-col gap-[18px]"
            @dragover.prevent
            @dragleave="onDragLeave"
            @drop.prevent="onDrop"
        >
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
                            {{ actLabel(block.act.num, block.act.minutes) }}
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
                        <template
                            v-for="{ scene, num, index } in block.act.scenes"
                            :key="scene.id"
                        >
                            <DropGap :open="showsGap(index)" />
                            <SceneCard
                                :scene-id="scene.id"
                                :num="num"
                                :dragging="dragId === scene.id"
                                @dragover="onDragOver(index, $event)"
                                @start-drag="startDrag(scene.id)"
                                @end-drag="endDrag"
                            />
                            <DropGap
                                v-if="index === plan.scenes.length - 1"
                                :open="showsGap(plan.scenes.length)"
                            />
                        </template>
                    </div>
                </div>

                <!-- The interval: a rule across the running order rather than a
                     card, because nothing about it is played. It drags and
                     deletes like a scene, and its length is opened by
                     clicking it. -->
                <template v-else>
                    <DropGap :open="showsGap(block.index)" />
                    <div
                        :class="[
                            'flex items-center gap-2.5 transition-opacity',
                            dragId === block.scene.id ? 'opacity-40' : '',
                        ]"
                        @dragover="onDragOver(block.index, $event)"
                    >
                        <span
                            class="h-0.5 flex-1 rounded-full bg-r10-orange/35"
                            aria-hidden="true"
                        ></span>
                        <DragHandle
                            class="text-r10-grey-500"
                            @dragstart="startDrag(block.scene.id)"
                            @dragend="endDrag"
                        />
                        <button
                            type="button"
                            title="Muuda vaheaja pikkust"
                            class="flex shrink-0 cursor-pointer items-center gap-2 rounded-full border-2 border-dashed border-r10-orange bg-white px-4 py-1.5 font-r10-body text-[11px] font-bold tracking-[0.06em] text-r10-navy uppercase transition hover:border-r10-orange-700 hover:text-r10-orange"
                            @click="editing = block.scene"
                        >
                            <Diamond :size="8" />
                            Vaheaeg · {{ block.minutes }} min
                        </button>
                        <button
                            type="button"
                            title="Kustuta vaheaeg"
                            aria-label="Kustuta vaheaeg"
                            class="flex shrink-0 cursor-pointer items-center justify-center rounded-full border border-r10-grey-200 bg-white p-1.5 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                            @click="removeIntermission(block.scene)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                        <span
                            class="h-0.5 flex-1 rounded-full bg-r10-orange/35"
                            aria-hidden="true"
                        ></span>
                    </div>
                    <DropGap
                        v-if="block.index === plan.scenes.length - 1"
                        :open="showsGap(plan.scenes.length)"
                    />
                </template>
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

        <!-- The parts are read off the show's own running time, so naming them
             longer than the evening leaves the closing part with nothing. Only
             the performer can settle which number is wrong. -->
        <R10Notice v-if="partsOverrun" class="mt-[18px]">
            Etenduse osade ja vaheaegade pikkused täidavad kogu etenduse aja ({{
                showMinutes
            }}
            min), nii et viimasele osale ei jää midagi. Kontrolli osade pikkusi.
        </R10Notice>

        <IntermissionDialog v-model="editing" />
    </section>
</template>
