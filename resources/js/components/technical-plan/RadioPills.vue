<script setup lang="ts" generic="T extends string">
defineProps<{
    modelValue: T;
    options: { value: T; label: string }[];
}>();

defineEmits<{ 'update:modelValue': [value: T] }>();
</script>

<template>
    <div class="flex flex-wrap gap-2.5">
        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            :class="[
                'cursor-pointer rounded-full border-2 px-[18px] py-2.5 font-r10-body text-[13px] font-bold tracking-[0.04em] uppercase transition',
                modelValue === opt.value
                    ? 'border-r10-orange bg-r10-orange text-r10-navy'
                    : 'border-r10-grey-200 bg-white text-r10-grey-700 hover:border-r10-navy-300',
            ]"
            @click="$emit('update:modelValue', opt.value)"
        >
            {{ opt.label }}
        </button>
    </div>
</template>
