<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 10 });
</script>

<template>
    <span
        class="inline-block shrink-0 rotate-45 rounded-[2px] bg-r10-orange"
        :style="{ width: size + 'px', height: size + 'px' }"
    />
</template>
