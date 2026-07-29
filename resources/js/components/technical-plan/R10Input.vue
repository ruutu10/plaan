<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * A text field wearing the R10 styling, in either of the two ways this app
 * submits forms: bound with `v-model` for the JSON screens, or left
 * uncontrolled with a `name` for the Inertia `<Form>` screens, which read the
 * value off the form element itself.
 */
/** Extra attributes belong on the field itself, not on the wrapping label. */
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        label?: string;
        hint?: string;
        required?: boolean;
        type?: string;
        placeholder?: string;
        /** Validation message for this field, shown beneath it. */
        error?: string;
        errorTestId?: string;
        /** Field name, for a form submitted natively rather than bound. */
        name?: string;
        defaultValue?: string | number;
        autocomplete?: string;
        modelValue?: string | number | null;
    }>(),
    {
        type: 'text',
        required: false,
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

/** Holds the value when nothing is bound, so typing is not undone by a render. */
const uncontrolled = ref(props.defaultValue ?? '');

const value = computed(() =>
    props.modelValue !== undefined ? props.modelValue : uncontrolled.value,
);

function write(event: Event): void {
    const next = (event.target as HTMLInputElement).value;

    uncontrolled.value = next;
    emit('update:modelValue', next);
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
        <input
            v-bind="$attrs"
            :type="type"
            :name="name"
            :value="value"
            :required="required"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange"
            @input="write"
        />
        <span
            v-if="error"
            :data-test="errorTestId"
            class="text-xs font-medium text-r10-orange-700"
        >
            {{ error }}
        </span>
    </label>
</template>
