<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmVisit from '@/components/technical-plan/R10ConfirmVisit.vue';
import { destroy as destroyInvitation } from '@/routes/teams/invitations';
import type { Team, TeamInvitation } from '@/types';

const props = defineProps<{ team: Team; invitation: TeamInvitation | null }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() =>
    props.invitation
        ? destroyInvitation([props.team.slug, props.invitation.code])
        : null,
);
</script>

<template>
    <R10ConfirmVisit
        v-model:open="open"
        title="Tühista kutse"
        confirm-label="Tühista kutse"
        cancel-label="Jäta alles"
        :action="action"
        test-id="cancel-invitation-confirm"
    >
        <template #description>
            Kas tühistada kutse aadressile
            <strong>{{ invitation?.email }}</strong
            >? Link, mille saaja juba sai, lakkab kehtimast.
        </template>
    </R10ConfirmVisit>
</template>
