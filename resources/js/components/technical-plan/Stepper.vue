<script setup lang="ts">
import { computed } from 'vue';
import { STEP_LABELS } from './plan';

const props = withDefaults(
    defineProps<{ step: number; optionalSteps?: number[] }>(),
    { optionalSteps: () => [] },
);

const emit = defineEmits<{ go: [index: number]; reset: [] }>();

const progress = computed(() =>
    Math.round((props.step / (STEP_LABELS.length - 1)) * 100),
);

function stateOf(index: number): 'active' | 'done' | 'todo' {
    if (index === props.step) {
        return 'active';
    }

    return index < props.step ? 'done' : 'todo';
}

function isOptional(index: number): boolean {
    return props.optionalSteps.includes(index + 1);
}
</script>

<template>
    <aside
        class="r10-no-print sticky top-24 flex w-full flex-col gap-0 lg:w-[190px] lg:shrink-0"
    >
        <div class="mb-3.5">
            <div class="h-[5px] overflow-hidden rounded-full bg-r10-grey-200">
                <div
                    class="h-full rounded-full bg-r10-orange transition-[width] duration-300"
                    :style="{ width: progress + '%' }"
                />
            </div>
            <div
                class="mt-2 text-xs font-bold tracking-[0.1em] text-r10-grey-500 uppercase"
            >
                {{ progress }}% valmis
            </div>
        </div>

        <button
            v-for="(label, index) in STEP_LABELS"
            :key="index"
            type="button"
            :class="[
                'flex min-h-[46px] w-full cursor-pointer items-stretch gap-3 rounded-[10px] border-none px-2.5 py-0.5 text-left transition-colors',
                stateOf(index) === 'active'
                    ? 'bg-r10-orange-100'
                    : 'bg-transparent',
            ]"
            @click="emit('go', index)"
        >
            <span class="flex w-7 shrink-0 flex-col items-center self-stretch">
                <span
                    class="w-0.5 flex-1"
                    :class="index <= step ? 'bg-r10-navy' : 'bg-r10-grey-200'"
                    :style="{ visibility: index === 0 ? 'hidden' : 'visible' }"
                />
                <span
                    class="z-[1] inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 font-r10-display text-[13px] font-extrabold"
                    :class="{
                        'border-r10-orange bg-r10-orange text-r10-navy':
                            stateOf(index) === 'active',
                        'border-r10-navy bg-r10-navy text-white':
                            stateOf(index) === 'done',
                        'border-r10-grey-200 bg-white text-r10-grey-500':
                            stateOf(index) === 'todo',
                        'border-dashed': isOptional(index),
                    }"
                >
                    {{ index + 1 }}
                </span>
                <span
                    class="w-0.5 flex-1"
                    :class="index < step ? 'bg-r10-navy' : 'bg-r10-grey-200'"
                    :style="{
                        visibility:
                            index === STEP_LABELS.length - 1
                                ? 'hidden'
                                : 'visible',
                    }"
                />
            </span>
            <span
                class="flex items-center font-r10-body text-sm font-bold tracking-[0.02em]"
                :class="
                    stateOf(index) === 'todo'
                        ? 'text-r10-grey-500'
                        : 'text-r10-ink'
                "
            >
                {{ label }}
            </span>
        </button>

        <button
            type="button"
            class="mt-4 cursor-pointer self-start border-none bg-transparent px-0 py-1.5 font-r10-body text-xs font-bold tracking-[0.06em] text-r10-grey-500 uppercase underline"
            @click="emit('reset')"
        >
            Alusta otsast
        </button>
    </aside>
</template>
