<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import type { BreadcrumbItem } from '@/types';

/** The way back out of an edit screen, to the listing it was opened from. */
withDefaults(defineProps<{ href: BreadcrumbItem['href']; label?: string }>(), {
    label: 'Tagasi',
});
</script>

<template>
    <R10Button :href="href" variant="outline" size="sm">
        <ArrowLeft class="h-3.5 w-3.5" />
        {{ label }}
    </R10Button>
</template>
