<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Hele' },
    { value: 'dark', Icon: Moon, label: 'Tume' },
    { value: 'system', Icon: Monitor, label: 'Süsteemne' },
] as const;
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-r10-grey-100 p-1">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'flex cursor-pointer items-center rounded-md px-3.5 py-1.5 font-r10-body transition-colors',
                appearance === value
                    ? 'bg-white text-r10-navy shadow-xs'
                    : 'text-r10-grey-500 hover:bg-r10-grey-200/60 hover:text-r10-ink',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
