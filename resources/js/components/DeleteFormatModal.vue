<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import PlankaReimportField from '@/components/PlankaReimportField.vue';
import R10ConfirmDelete from '@/components/technical-plan/R10ConfirmDelete.vue';
import { destroy } from '@/routes/api/formats';
import type { Format } from '@/types';

const props = defineProps<{ format: Format | null }>();

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

/** Put aside rather than wiped — see {@see PlankaReimportField}. */
const keepDeleted = ref(true);

// The dialog is mounted with the page, not with the row, so the choice is put
// back to its default every time it is opened: a wipe asked for once must not
// still be asked for on the next format the user reaches for.
watch(open, (isOpen) => {
    if (isOpen) {
        keepDeleted.value = true;
    }
});

const action = computed(() =>
    props.format
        ? destroy(props.format.id, { query: { force: !keepDeleted.value } })
        : null,
);

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
            Kas kustutada formaat „{{ format?.name }}“?
            <template v-if="keepDeleted">
                See kaob nimekirjast, kuid jääb andmebaasi alles.
            </template>
            <template v-else>
                See kustutatakse andmebaasist jäädavalt.
            </template>
        </template>

        <template v-if="performanceCount > 0 || !keepDeleted" #warning>
            <template v-if="keepDeleted">
                Koos formaadiga kustutatakse ka selle
                {{ performanceCount }} etendust. Neile esitatud tehnikaplaanid
                jäävad alles.
            </template>
            <template v-else>
                Koos formaadiga kustutatakse jäädavalt ka selle
                {{ performanceCount }} etendust ja kõik neile esitatud
                tehnikaplaanid. Seda ei saa tagasi võtta.
            </template>
        </template>

        <PlankaReimportField v-model="keepDeleted" />
    </R10ConfirmDelete>
</template>
