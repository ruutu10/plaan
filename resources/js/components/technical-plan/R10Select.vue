<script setup lang="ts">
/**
 * A dropdown wearing the R10 field styling. The label wraps the control, so
 * two of these can sit on one page without needing ids to tell them apart.
 */
const props = withDefaults(
    defineProps<{
        label?: string;
        hint?: string;
        required?: boolean;
        disabled?: boolean;
        /** Validation message for this field, shown beneath it. */
        error?: string;
        errorTestId?: string;
        options: { value: string | number; label: string }[];
        /** Shown first and unselectable, for a field with nothing chosen yet. */
        placeholder?: string;
        /** Field name, for a form submitted natively rather than bound. */
        name?: string;
        modelValue: string | number | null;
    }>(),
    { required: false, disabled: false },
);

const emit = defineEmits<{ 'update:modelValue': [value: string | number] }>();

defineOptions({ inheritAttrs: false });

/**
 * The DOM hands back a string, but the options may be keyed by number — a team
 * id, say — so the chosen option's own value is emitted rather than the string.
 */
function choose(event: Event): void {
    const chosen = (event.target as HTMLSelectElement).value;
    const option = props.options.find((row) => String(row.value) === chosen);

    emit('update:modelValue', option ? option.value : chosen);
}
</script>

<template>
    <label class="flex flex-col gap-1.5">
        <span
            v-if="label"
            class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
        >
            {{ label }}
            <span v-if="required" class="text-r10-orange">*</span>
        </span>
        <span v-if="hint" class="-mt-0.5 text-xs text-r10-grey-500">{{
            hint
        }}</span>
        <select
            v-bind="$attrs"
            :name="name"
            :value="modelValue"
            :disabled="disabled"
            class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange disabled:opacity-50"
            @change="choose"
        >
            <option v-if="placeholder" :value="null" disabled>
                {{ placeholder }}
            </option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
        <span
            v-if="error"
            :data-test="errorTestId"
            class="text-xs font-medium text-r10-orange-700"
        >
            {{ error }}
        </span>
    </label>
</template>
