<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import TeamInvitationController from '@/actions/App/Http/Controllers/Teams/TeamInvitationController';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Dialog from '@/components/technical-plan/R10Dialog.vue';
import type { DashboardInvitation } from '@/types';

type Props = {
    invitations: DashboardInvitation[];
};

const props = defineProps<Props>();

const open = ref(true);
const processingCode = ref<string | null>(null);

const acceptInvitation = (invitation: DashboardInvitation) => {
    router.visit(TeamInvitationController.accept(invitation), {
        onStart: () => (processingCode.value = invitation.code),
        onFinish: () => (processingCode.value = null),
    });
};

const declineInvitation = (invitation: DashboardInvitation) => {
    router.visit(TeamInvitationController.decline(invitation), {
        onStart: () => (processingCode.value = invitation.code),
        onFinish: () => (processingCode.value = null),
        onSuccess: () => {
            if (props.invitations.length === 1) {
                open.value = false;
            }
        },
    });
};
</script>

<template>
    <R10Dialog
        v-model:open="open"
        title="Pending team invitations"
        description="Accept or decline the teams you have been invited to join."
    >
        <!-- The marker sits on the body: `R10Dialog`'s root is renderless, so
             an attribute on the component itself would not reach the DOM. -->
        <div data-test="pending-invitations-modal" class="grid gap-4">
            <div
                v-for="invitation in props.invitations"
                :key="invitation.code"
                data-test="pending-invitation-row"
                class="rounded-[14px] border border-r10-grey-200 bg-white p-4"
            >
                <div class="space-y-1">
                    <p
                        class="font-r10-display text-base font-semibold text-r10-ink"
                    >
                        {{ invitation.team.name }}
                    </p>
                    <p class="text-sm text-r10-grey-500">
                        {{ invitation.inviterName }} invited you to join this
                        team.
                    </p>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <R10Button
                        variant="outline"
                        size="sm"
                        data-test="pending-invitation-decline"
                        :disabled="processingCode === invitation.code"
                        @click="declineInvitation(invitation)"
                    >
                        Decline
                    </R10Button>

                    <R10Button
                        size="sm"
                        data-test="pending-invitation-accept"
                        :disabled="processingCode === invitation.code"
                        @click="acceptInvitation(invitation)"
                    >
                        Accept
                    </R10Button>
                </div>
            </div>
        </div>
    </R10Dialog>
</template>
