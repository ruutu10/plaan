<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmDelete from '@/components/technical-plan/R10ConfirmDelete.vue';
import { destroy } from '@/routes/api/formats/performances/staff';
import type { PerformanceStaffMember } from '@/types';

/**
 * Take one hand-added job off a performance's staff. The import's own rows are
 * never offered here — they are the card's to say.
 */
const props = defineProps<{
    formatId: number;
    performanceId: number;
    member: PerformanceStaffMember | null;
}>();

const emit = defineEmits<{ removed: [] }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() =>
    props.member
        ? destroy([
              props.formatId,
              props.performanceId,
              props.member.staffingId,
          ])
        : null,
);
</script>

<template>
    <R10ConfirmDelete
        v-model:open="open"
        title="Eemalda meeskonnast"
        :action="action"
        confirm-label="Eemalda"
        success-toast="Meeskonnaliige eemaldatud."
        error-toast="Eemaldamine ebaõnnestus. Proovi uuesti."
        test-id-prefix="remove-staff"
        @deleted="emit('removed')"
    >
        <template #description>
            Kas eemaldada {{ member?.name }} rollist „{{ member?.roleLabel }}“?
        </template>
    </R10ConfirmDelete>
</template>
