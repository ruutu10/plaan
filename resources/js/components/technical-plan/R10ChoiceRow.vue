<script setup lang="ts" generic="T extends string | number">
/**
 * One choice in a list of them, as a whole clickable row wearing the R10 card
 * styling: a checkbox for picking several (recipients), a radio for picking
 * one (a job). Bound with `v-model` the way the native input would be — an
 * array of the chosen values for checkboxes, the one chosen value for radios.
 *
 * The default slot is for whatever the row shows after its label, pushed to
 * the far end — the roles a recipient holds, say.
 */
withDefaults(
    defineProps<{
        type?: 'checkbox' | 'radio';
        value: T;
        label: string;
        /** The group a radio belongs to, so the browser lets only one be on. */
        name?: string;
        inputTestId?: string;
    }>(),
    { type: 'checkbox' },
);

/** The chosen values for a checkbox row, the one chosen value for a radio. */
const model = defineModel<T[] | T | null>({ required: true });
</script>

<template>
    <label
        class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-r10-grey-200 bg-white p-4 has-checked:border-r10-orange"
    >
        <input
            v-model="model"
            :type="type"
            :name="name"
            :value="value"
            :data-test="inputTestId"
            class="h-4 w-4 shrink-0 cursor-pointer accent-r10-orange"
        />
        <span class="text-sm font-bold text-r10-ink">{{ label }}</span>
        <span
            v-if="$slots.default"
            class="ml-auto flex flex-wrap justify-end gap-1"
        >
            <slot />
        </span>
    </label>
</template>
