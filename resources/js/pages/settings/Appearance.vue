<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Välimuse seaded',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Välimuse seaded" />

    <h1 class="sr-only">Välimuse seaded</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Välimuse seaded"
            description="Muuda oma konto välimuse seadeid"
        />
        <AppearanceTabs />
    </div>
</template>
