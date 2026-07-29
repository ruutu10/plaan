<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="font-r10-body text-sm font-medium text-r10-orange-700">
            {{ message }}
        </p>
    </div>
</template>
