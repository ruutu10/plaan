<script setup lang="ts">
withDefaults(
    defineProps<{
        label?: string;
        hint?: string;
        placeholder?: string;
        minHeight?: string;
        disabled?: boolean;
        /** Validation message for this field, shown beneath it. */
        error?: string;
        modelValue: string;
    }>(),
    {
        minHeight: '128px',
        disabled: false,
    },
);

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <label class="flex flex-col gap-1.5">
        <span
            v-if="label"
            class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
        >
            {{ label }}
        </span>
        <span v-if="hint" class="-mt-0.5 text-xs text-r10-grey-500">{{
            hint
        }}</span>
        <textarea
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :style="{ minHeight }"
            class="w-full resize-y rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] leading-relaxed text-r10-ink outline-none focus:border-r10-orange disabled:opacity-50"
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLTextAreaElement).value,
                )
            "
        ></textarea>
        <span v-if="error" class="text-xs font-medium text-r10-orange-700">
            {{ error }}
        </span>
    </label>
</template>
