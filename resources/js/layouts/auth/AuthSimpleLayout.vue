<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-r10-paper p-6 font-r10-body text-r10-grey-700 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-5">
                    <Link
                        :href="home()"
                        class="font-r10-display text-2xl font-black tracking-[0.06em] text-r10-navy"
                    >
                        RUUTU<span class="text-r10-orange">10</span>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                </div>

                <div
                    class="rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
                >
                    <div class="mb-6 space-y-2 text-center">
                        <h1
                            class="font-r10-display text-xl font-bold tracking-[0.03em] text-r10-navy uppercase"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-sm leading-relaxed text-r10-grey-700">
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
