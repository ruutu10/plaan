<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <h2
            class="font-r10-display font-bold tracking-[0.03em] text-r10-navy uppercase"
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-base'
                    : 'text-xl tracking-[0.02em]'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="font-r10-body text-sm text-r10-grey-500">
            {{ description }}
        </p>
    </header>
</template>
