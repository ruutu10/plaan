<script setup lang="ts">
import Diamond from './Diamond.vue';

/**
 * Where a dragged entry of a list would land if let go now: a line in a space
 * that opens between two entries. Always mounted, so the space can open and
 * close smoothly; while shut, its negative margins cancel the flex gap it sits
 * in (`spacing`, in pixels), so the list looks exactly as it does without it.
 */
withDefaults(defineProps<{ open: boolean; spacing?: number }>(), {
    spacing: 18,
});
</script>

<template>
    <div
        aria-hidden="true"
        :class="[
            'flex items-center gap-2 transition-all duration-150 ease-out',
            open ? 'h-5 opacity-100' : 'h-0 opacity-0',
        ]"
        :style="{ marginBlock: open ? '0px' : `-${spacing / 2}px` }"
    >
        <Diamond :size="9" />
        <span class="h-[3px] flex-1 rounded-full bg-r10-orange"></span>
        <Diamond :size="9" />
    </div>
</template>
