<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'outline';
        /** `sm` is slim enough for an icon-only button. */
        size?: 'sm' | 'md' | 'lg';
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        type: 'button',
        disabled: false,
    },
);

const classes = computed(() => {
    const variant =
        props.variant === 'primary'
            ? 'bg-r10-orange text-r10-navy hover:bg-r10-orange-600'
            : 'bg-white text-r10-navy border-2 border-r10-navy hover:bg-r10-navy hover:text-white';

    const size = {
        sm: 'text-xs px-3 py-2',
        md: 'text-sm px-6 py-3',
        lg: 'text-[15px] px-7 py-[15px]',
    }[props.size];

    return `${variant} ${size}`;
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="[
            'inline-flex cursor-pointer items-center justify-center gap-2 rounded-full font-r10-body font-bold tracking-[0.04em] uppercase transition disabled:pointer-events-none disabled:opacity-50',
            classes,
        ]"
    >
        <slot />
    </button>
</template>
