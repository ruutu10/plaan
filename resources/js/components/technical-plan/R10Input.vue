<script setup lang="ts">
withDefaults(
    defineProps<{
        label?: string;
        hint?: string;
        required?: boolean;
        type?: string;
        placeholder?: string;
        modelValue: string | number | null;
    }>(),
    {
        type: 'text',
        required: false,
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
            <span v-if="required" class="text-r10-orange">*</span>
        </span>
        <span v-if="hint" class="-mt-0.5 text-xs text-r10-grey-500">{{
            hint
        }}</span>
        <input
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange"
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
        />
    </label>
</template>
