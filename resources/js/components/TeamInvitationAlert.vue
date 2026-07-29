<script setup lang="ts">
import R10Notice from '@/components/technical-plan/R10Notice.vue';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: 'login' | 'register';
};

defineProps<Props>();
</script>

<template>
    <div data-test="team-invitation-alert">
        <!-- `busy` spins its marker, so the still navy panel reads better here. -->
        <R10Notice tone="success">
            <template v-if="action === 'login'">
                Tiimiga „{{ invitation.teamName }}“ liitumiseks logi sisse.
            </template>
            <template v-else>
                Tiimiga „{{ invitation.teamName }}“ liitumiseks registreeru.
            </template>
        </R10Notice>
    </div>
</template>
