<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const appVersion = computed(() => page.props.appVersion);
</script>

<template>
    <footer
        class="border-t border-r10-grey-200 py-4 text-center text-xs font-r10-body text-xs text-r10-grey-500"
    >
        versioon v{{ appVersion }}
    </footer>
</template>
