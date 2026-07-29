<script setup lang="ts">
import { computed } from 'vue';
import R10Notice from '@/components/technical-plan/R10Notice.vue';

type Props = {
    errors: string[];
    title?: string;
};

const props = withDefaults(defineProps<Props>(), {
    title: 'Midagi läks valesti.',
});

const uniqueErrors = computed(() => Array.from(new Set(props.errors)));
</script>

<template>
    <R10Notice tone="error">
        <p class="font-bold">{{ title }}</p>
        <ul class="mt-1 list-inside list-disc text-sm">
            <li v-for="(error, index) in uniqueErrors" :key="index">
                {{ error }}
            </li>
        </ul>
    </R10Notice>
</template>
