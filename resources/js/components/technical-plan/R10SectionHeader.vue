<script setup lang="ts">
/**
 * The heading of a section within a management screen: a title, an optional
 * lead, and room on the right for the section's own action.
 */
defineProps<{ title: string; lead?: string }>();
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2
                class="m-0 font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
            >
                {{ title }}
            </h2>
            <p v-if="lead" class="mt-1 text-[15px] text-r10-grey-500">
                {{ lead }}
            </p>
        </div>

        <slot name="action" />
    </div>
</template>
