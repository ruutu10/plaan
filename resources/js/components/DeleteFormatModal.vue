<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmDelete from '@/components/technical-plan/R10ConfirmDelete.vue';
import { destroy } from '@/routes/api/shows';
import type { Show } from '@/types';

const props = defineProps<{ show: Show | null }>();

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() => (props.show ? destroy(props.show.id) : null));

const performanceCount = computed(() => props.show?.performanceCount ?? 0);
</script>

<template>
    <R10ConfirmDelete
        v-model:open="open"
        title="Kustuta lavastus"
        :action="action"
        success-toast="Lavastus kustutatud."
        test-id-prefix="show-delete"
        @deleted="emit('deleted')"
    >
        <template #description>
            Kas kustutada lavastus „{{ show?.name }}“? See kaob nimekirjast,
            kuid jääb andmebaasi alles.
        </template>

        <template v-if="performanceCount > 0" #warning>
            Koos lavastusega kustutatakse ka selle
            {{ performanceCount }} etendust. Neile esitatud tehnikaplaanid
            jäävad alles.
        </template>
    </R10ConfirmDelete>
</template>
