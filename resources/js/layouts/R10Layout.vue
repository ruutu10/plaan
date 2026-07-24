<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        title?: string;
        noPrintHeader?: boolean;
    }>(),
    {
        title: '',
        noPrintHeader: false,
    },
);
</script>

<template>
    <div class="min-h-screen bg-r10-paper font-r10-body text-r10-grey-700">
        <header
            class="sticky top-0 z-20 border-b border-white/15 bg-r10-navy"
            :class="{ 'r10-no-print': noPrintHeader }"
        >
            <div
                class="mx-auto flex max-w-[1160px] items-center gap-5 px-6 py-3.5"
            >
                <Link
                    href="/"
                    class="font-r10-display text-xl font-black tracking-[0.06em] text-white"
                >
                    RUUTU<span class="text-r10-orange">10</span>
                </Link>

                <template v-if="title">
                    <div class="h-6 w-px bg-white/15" />
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span
                            class="font-r10-display text-[15px] leading-none font-semibold tracking-[0.03em] text-white uppercase"
                        >
                            {{ title }}
                        </span>
                    </div>
                </template>

                <div class="ml-auto flex items-center gap-4">
                    <slot name="actions" />
                </div>
            </div>
        </header>

        <slot />
    </div>
</template>
