<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmDelete from '@/components/technical-plan/R10ConfirmDelete.vue';
import { destroy } from '@/routes/api/formats';
import type { Format } from '@/types';

const props = defineProps<{ format: Format | null }>();

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() => (props.format ? destroy(props.format.id) : null));

const performanceCount = computed(() => props.format?.performanceCount ?? 0);
</script>

<template>
    <R10ConfirmDelete
        v-model:open="open"
        title="Kustuta formaat"
        :action="action"
        success-toast="Formaat kustutatud."
        test-id-prefix="format-delete"
        @deleted="emit('deleted')"
    >
        <template #description>
            Kas kustutada formaat „{{ format?.name }}“? See kaob nimekirjast,
            kuid jääb andmebaasi alles.
        </template>

        <template v-if="performanceCount > 0" #warning>
            Koos formaadiga kustutatakse ka selle
            {{ performanceCount }} etendust. Neile esitatud tehnikaplaanid
            jäävad alles.
        </template>
    </R10ConfirmDelete>
</template>
