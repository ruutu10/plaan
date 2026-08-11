<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import PlankaReimportField from '@/components/PlankaReimportField.vue';
import R10ConfirmDelete from '@/components/technical-plan/R10ConfirmDelete.vue';
import { formatLocalDate } from '@/lib/date';
import { destroy } from '@/routes/api/formats/performances';
import type { Performance } from '@/types';

const props = defineProps<{
    formatId: number;
    performance: Performance | null;
}>();

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

/** Put aside rather than wiped — see {@see PlankaReimportField}. */
const keepDeleted = ref(true);

// Put back to its default every time the dialog opens: the dialog is mounted
// with the page, not with the row, so a wipe asked for once must not still be
// asked for on the next performance the user reaches for.
watch(open, (isOpen) => {
    if (isOpen) {
        keepDeleted.value = true;
    }
});

const action = computed(() =>
    props.performance
        ? destroy([props.formatId, props.performance.id], {
              query: { force: !keepDeleted.value },
          })
        : null,
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
            Kas kustutada
            {{ formatLocalDate(performance?.startsAt) }} etendus? Seda ei saa
            tagasi võtta.
        </template>

        <template v-if="planCount > 0" #warning>
            <template v-if="keepDeleted">
                Sellele etendusele on esitatud {{ planCount }} tehnikaplaani.
                Plaanid jäävad alles, kuid etendus kaob nende juurest.
            </template>
            <template v-else>
                Sellele etendusele on esitatud {{ planCount }} tehnikaplaani.
                Koos etendusega kustutatakse jäädavalt ka need.
            </template>
        </template>

        <PlankaReimportField v-model="keepDeleted" />
    </R10ConfirmDelete>
</template>
