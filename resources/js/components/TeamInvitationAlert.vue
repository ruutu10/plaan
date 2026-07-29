<script setup lang="ts">
import R10Notice from '@/components/technical-plan/R10Notice.vue';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: 'Log in' | 'Register';
};

defineProps<Props>();
</script>

<template>
    <div data-test="team-invitation-alert">
        <!-- `busy` spins its marker, so the still navy panel reads better here. -->
        <R10Notice tone="success">
            {{ action }} to join the "{{ invitation.teamName }}" team.
        </R10Notice>
    </div>
</template>
