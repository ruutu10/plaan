<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmDelete from '@/components/technical-plan/R10ConfirmDelete.vue';
import { destroy } from '@/routes/api/teams/members';
import type { ManagedTeamMember } from '@/types';

const props = defineProps<{
    teamId: number;
    member: ManagedTeamMember | null;
}>();

const emit = defineEmits<{ removed: [] }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() =>
    props.member ? destroy([props.teamId, props.member.id]) : null,
);
</script>

<template>
    <R10ConfirmDelete
        v-model:open="open"
        title="Eemalda liige"
        :action="action"
        confirm-label="Eemalda"
        success-toast="Liige eemaldatud."
        error-toast="Eemaldamine ebaõnnestus. Proovi uuesti."
        test-id-prefix="remove-member"
        @deleted="emit('removed')"
    >
        <template #description>
            Kas eemaldada {{ member?.name }} tiimist? Konto ise jääb alles ja ta
            saab tiimi hiljem tagasi lisada.
        </template>
    </R10ConfirmDelete>
</template>
