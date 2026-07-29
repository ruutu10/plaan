<script setup lang="ts">
import { computed } from 'vue';

/**
 * A small status or role label. `tone` says how much it should stand out —
 * `muted` for a fact about a row, `accent` for something that wants noticing.
 */
const props = withDefaults(
    defineProps<{
        tone?: 'muted' | 'neutral' | 'accent' | 'navy';
        size?: 'sm' | 'md';
    }>(),
    { tone: 'muted', size: 'sm' },
);

const toneClass = computed(
    () =>
        ({
            muted: 'border-r10-grey-200 bg-white text-r10-grey-500',
            neutral: 'border-r10-grey-200 bg-r10-grey-100 text-r10-grey-700',
            accent: 'border-r10-orange bg-r10-orange-100 text-r10-orange-700',
            navy: 'border-r10-navy-200 bg-r10-navy-100 text-r10-navy-700',
        })[props.tone],
);

const sizeClass = computed(() =>
    props.size === 'sm'
        ? 'border px-2 py-0.5 text-[11px]'
        : 'border-2 px-3 py-1 text-[11px]',
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full font-r10-body font-bold tracking-[0.08em] whitespace-nowrap uppercase"
        :class="[toneClass, sizeClass]"
    >
        <slot />
    </span>
</template>
