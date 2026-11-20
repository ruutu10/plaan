<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';

/**
 * A tick-box bringing back rows a listing hides by default — the archived
 * nights, say — and counting how many there are. With none to bring back it
 * shows nothing, so a page can offer it unconditionally.
 */
defineProps<{
    /** What ticking it shows, e.g. «arhiveerituid». */
    label: string;
    count: number;
}>();

/** Extra attributes, such as a `data-test`, belong on the tick-box itself. */
defineOptions({ inheritAttrs: false });

const shown = defineModel<boolean>({ required: true });
</script>

<template>
    <label
        v-if="count > 0"
        class="flex items-center gap-2 text-sm text-r10-grey-700"
    >
        <Checkbox v-model="shown" v-bind="$attrs" />
        <span>Näita {{ label }} ({{ count }})</span>
    </label>
</template>
