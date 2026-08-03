<script setup lang="ts">
import Diamond from './Diamond.vue';

defineProps<{
    eyebrow: string;
    title: string;
    lead?: string;
}>();
</script>

<template>
    <div>
        <div class="mb-3 flex items-center gap-2">
            <Diamond :size="10" />
            <span
                class="font-r10-body text-xs font-bold tracking-[0.18em] text-r10-orange uppercase"
            >
                {{ eyebrow }}
            </span>
        </div>
        <h1
            class="m-0 font-r10-display text-[26px] leading-[1.05] font-bold tracking-[0.02em] break-words text-r10-ink uppercase sm:text-[34px]"
        >
            {{ title }}
        </h1>
        <p
            v-if="lead"
            class="mt-2.5 mb-7 font-r10-body text-[15px] leading-relaxed font-light text-r10-grey-700 sm:text-[17px]"
        >
            {{ lead }}
        </p>
    </div>
</template>
