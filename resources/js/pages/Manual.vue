<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import R10Layout from '@/layouts/R10Layout.vue';

defineProps<{
    /** MANUAL.md, already rendered to HTML on the server. */
    html: string;
}>();
</script>

<template>
    <Head title="Abi" />

    <R10Layout title="Abi">
        <main class="mx-auto max-w-[860px] px-4 pt-10 pb-16 sm:px-6">
            <article
                class="markdown rounded-[22px] border border-r10-grey-200 bg-white px-5 py-8 shadow-[0_6px_18px_rgba(10,14,23,0.1)] sm:px-10 sm:py-12"
                v-html="html"
            ></article>
        </main>
    </R10Layout>
</template>
