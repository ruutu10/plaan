<script setup lang="ts">
/**
 * The six-dot grip an entry of a list is dragged by. Its colour is the
 * caller's, set with a text colour class.
 */
withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), { size: 'sm' });
</script>

<template>
    <span
        draggable="true"
        title="Lohista ümberjärjestamiseks"
        class="flex shrink-0 cursor-grab"
    >
        <svg
            :width="size === 'md' ? 12 : 10"
            :height="size === 'md' ? 18 : 16"
            viewBox="0 0 12 18"
            fill="currentColor"
        >
            <circle cx="3" cy="3" r="1.5" />
            <circle cx="9" cy="3" r="1.5" />
            <circle cx="3" cy="9" r="1.5" />
            <circle cx="9" cy="9" r="1.5" />
            <circle cx="3" cy="15" r="1.5" />
            <circle cx="9" cy="15" r="1.5" />
        </svg>
    </span>
</template>
