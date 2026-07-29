<script setup lang="ts">
import { computed } from 'vue';

/**
 * A short message set off from the page — a failure, something in progress, or
 * a confirmation. The diamond is the R10 marker; on `busy` it spins.
 */
const props = withDefaults(
    defineProps<{ tone?: 'error' | 'busy' | 'success' }>(),
    { tone: 'error' },
);

const box = computed(
    () =>
        ({
            error: 'border border-r10-orange bg-r10-orange-100 px-[18px] py-3.5 items-start',
            busy: 'border border-r10-grey-200 bg-r10-grey-100 px-5 py-4 items-center',
            success: 'bg-r10-navy px-5 py-4 items-start',
        })[props.tone],
);

const marker = computed(
    () =>
        ({
            error: 'mt-[5px] bg-r10-error',
            busy: 'animate-[r10spin_1s_linear_infinite] bg-r10-orange',
            success: 'mt-[5px] bg-r10-orange',
        })[props.tone],
);

const text = computed(
    () =>
        ({
            error: 'text-sm leading-normal text-r10-navy',
            busy: 'text-sm text-r10-grey-700',
            success: 'text-white',
        })[props.tone],
);
</script>

<template>
    <div
        :role="tone === 'error' ? 'alert' : undefined"
        class="flex gap-2.5 rounded-[14px]"
        :class="box"
    >
        <span
            class="h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px]"
            :class="marker"
        />
        <div :class="text"><slot /></div>
    </div>
</template>
