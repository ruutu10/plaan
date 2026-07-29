<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

/** The way back out of an edit screen, to the listing it was opened from. */
withDefaults(defineProps<{ href: BreadcrumbItem['href']; label?: string }>(), {
    label: 'Tagasi',
});
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center gap-2 font-r10-body text-xs font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:text-r10-orange-700"
    >
        <ArrowLeft class="h-3.5 w-3.5" />
        {{ label }}
    </Link>
</template>
