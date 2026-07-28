<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmDelete from '@/components/technical-plan/R10ConfirmDelete.vue';
import { formatEstonianDate } from '@/lib/date';
import { destroy } from '@/routes/api/shows/performances';
import type { Performance } from '@/types';

const props = defineProps<{
    showId: number;
    performance: Performance | null;
}>();

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() =>
    props.performance ? destroy([props.showId, props.performance.id]) : null,
);

const planCount = computed(() => props.performance?.technicalPlanCount ?? 0);
</script>

<template>
    <R10ConfirmDelete
        v-model:open="open"
        title="Kustuta etendus"
        :action="action"
        success-toast="Etendus kustutatud."
        test-id-prefix="performance-delete"
        @deleted="emit('deleted')"
    >
        <template #description>
            Kas kustutada {{ formatEstonianDate(performance?.date) }} etendus?
            Seda ei saa tagasi võtta.
        </template>

        <template v-if="planCount > 0" #warning>
            Sellele etendusele on esitatud {{ planCount }} tehnikaplaani.
            Plaanid jäävad alles, kuid etendus kaob nende juurest.
        </template>
    </R10ConfirmDelete>
</template>
