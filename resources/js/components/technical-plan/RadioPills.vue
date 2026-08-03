<script setup lang="ts" generic="T extends string">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        modelValue: T;
        options: { value: T; label: string; icon?: Component }[];
        /** Tighter pills, for a choice nested inside a field. */
        compact?: boolean;
    }>(),
    { compact: false },
);

defineEmits<{ 'update:modelValue': [value: T] }>();
</script>

<template>
    <div class="flex flex-wrap" :class="compact ? 'gap-1.5' : 'gap-2.5'">
        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            :class="[
                'inline-flex cursor-pointer items-center rounded-full border-2 font-r10-body font-bold tracking-[0.04em] uppercase transition',
                compact
                    ? 'gap-1.5 px-3 py-1.5 text-[11px]'
                    : 'gap-2 px-[18px] py-2.5 text-[13px]',
                modelValue === opt.value
                    ? 'border-r10-orange bg-r10-orange text-r10-navy'
                    : 'border-r10-grey-200 bg-white text-r10-grey-700 hover:border-r10-navy-300',
            ]"
            @click="$emit('update:modelValue', opt.value)"
        >
            <component
                :is="opt.icon"
                v-if="opt.icon"
                :class="compact ? 'h-3.5 w-3.5' : 'h-4 w-4'"
            />
            {{ opt.label }}
        </button>
    </div>
</template>
