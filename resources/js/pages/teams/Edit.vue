<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Mail, UserPlus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import CancelInvitationModal from '@/components/CancelInvitationModal.vue';
import DeleteTeamModal from '@/components/DeleteTeamModal.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import R10BackLink from '@/components/technical-plan/R10BackLink.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10SectionHeader from '@/components/technical-plan/R10SectionHeader.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { edit, index, update } from '@/routes/teams';
import { update as updateMember } from '@/routes/teams/members';
import type {
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

const props = defineProps<{
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
}>();

defineOptions({
    layout: (props: { team: Team }) => ({
        breadcrumbs: [
            {
                title: 'Tiimid',
                href: index(),
            },
            {
                title: props.team.name,
                href: edit(props.team.slug),
            },
        ],
    }),
});

const inviteDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<TeamInvitation | null>(null);

const roleOptions = computed(() =>
    props.availableRoles.map((role) => ({
        value: role.value,
        label: role.label,
    })),
);

function changeRole(member: TeamMember, role: string | number): void {
    router.visit(updateMember([props.team.slug, member.id]), {
        data: { role: String(role) },
        preserveScroll: true,
    });
}

function confirmRemoveMember(member: TeamMember): void {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
}

function confirmCancelInvitation(invitation: TeamInvitation): void {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
}
</script>

<template>
    <Head :title="team.name" />

    <R10Page>
        <StepHeader
            eyebrow="Tiim"
            :title="team.name"
            :lead="
                permissions.canUpdateTeam
                    ? 'Muuda tiimi nime ja seda, kes tiimi kuuluvad.'
                    : 'Tiimi liikmed.'
            "
        />

        <Form
            v-if="permissions.canUpdateTeam"
            v-bind="update.form(team.slug)"
            v-slot="{ errors, processing }"
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
        >
            <R10Input
                name="name"
                label="Tiimi nimi"
                required
                :default-value="team.name"
                data-test="team-name-input"
                :error="errors.name"
            />

            <div class="flex items-center gap-3">
                <R10Button
                    type="submit"
                    data-test="team-save-button"
                    :disabled="processing"
                >
                    Salvesta
                </R10Button>

                <R10BackLink :href="index()" />
            </div>
        </Form>

        <!-- A reader who may not rename the team is shown no field to do it
             in; the header already carries the name. -->
        <R10BackLink v-else :href="index()" />

        <section class="mt-9 max-w-2xl">
            <R10SectionHeader
                title="Liikmed"
                lead="Tiimi kuuluvad kasutajad. Omanikku ei saa eemaldada ega tema rolli muuta."
                class="mb-4"
            >
                <template #action>
                    <R10Button
                        v-if="permissions.canCreateInvitation"
                        size="sm"
                        data-test="invite-member-button"
                        @click="inviteDialogOpen = true"
                    >
                        <UserPlus class="h-4 w-4" />
                        Kutsu liige
                    </R10Button>
                </template>
            </R10SectionHeader>

            <R10Table
                :columns="[
                    { label: 'Nimi' },
                    { label: 'Roll' },
                    { label: 'Tegevused', align: 'right', srOnly: true },
                ]"
                :rows="members"
                row-test-id="member-row"
                empty-text="Sellel tiimil pole ühtegi liiget."
                error-text="Liikmete laadimine ebaõnnestus. Proovi lehte värskendada."
            >
                <template #row="{ row: member }">
                    <td class="px-5 py-4 align-top">
                        <span class="font-medium text-r10-ink">
                            {{ member.name }}
                        </span>
                        <span
                            class="mt-0.5 block text-[13px] text-r10-grey-500"
                        >
                            {{ member.email }}
                        </span>
                    </td>
                    <td class="px-5 py-4 align-top">
                        <R10Select
                            v-if="
                                member.role !== 'owner' &&
                                permissions.canUpdateMember
                            "
                            :model-value="member.role"
                            :options="roleOptions"
                            data-test="member-role-trigger"
                            @update:model-value="changeRole(member, $event)"
                        />
                        <R10Pill v-else size="md">
                            {{ member.role_label }}
                        </R10Pill>
                    </td>
                    <td class="px-5 py-4 text-right align-top">
                        <button
                            v-if="
                                member.role !== 'owner' &&
                                permissions.canRemoveMember
                            "
                            type="button"
                            title="Eemalda liige"
                            data-test="member-remove-button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                            @click="confirmRemoveMember(member)"
                        >
                            <X class="h-3.5 w-3.5" />
                            <span class="sr-only">Eemalda</span>
                        </button>
                    </td>
                </template>
            </R10Table>
        </section>

        <section v-if="invitations.length > 0" class="mt-9 max-w-2xl">
            <R10SectionHeader
                title="Ootel kutsed"
                lead="Kutsed, mida pole veel vastu võetud."
                class="mb-4"
            />

            <R10Table
                :columns="[
                    { label: 'E-post' },
                    { label: 'Roll' },
                    { label: 'Tegevused', align: 'right', srOnly: true },
                ]"
                :rows="invitations"
                row-test-id="invitation-row"
                empty-text="Ootel kutseid pole."
                error-text="Kutsete laadimine ebaõnnestus. Proovi lehte värskendada."
            >
                <template #row="{ row: invitation }">
                    <td class="px-5 py-4 align-top">
                        <span class="flex items-center gap-2 text-r10-ink">
                            <Mail class="h-4 w-4 shrink-0 text-r10-grey-500" />
                            {{ invitation.email }}
                        </span>
                    </td>
                    <td class="px-5 py-4 align-top text-r10-grey-500">
                        {{ invitation.role_label }}
                    </td>
                    <td class="px-5 py-4 text-right align-top">
                        <button
                            v-if="permissions.canCancelInvitation"
                            type="button"
                            title="Tühista kutse"
                            data-test="invitation-cancel-button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                            @click="confirmCancelInvitation(invitation)"
                        >
                            <X class="h-3.5 w-3.5" />
                            <span class="sr-only">Tühista</span>
                        </button>
                    </td>
                </template>
            </R10Table>
        </section>

        <section v-if="permissions.canDeleteTeam" class="mt-9 max-w-2xl">
            <R10SectionHeader
                title="Kustuta tiim"
                lead="Tiimi kustutamine on lõplik ja seda ei saa tagasi võtta."
                class="mb-4"
            />

            <div
                class="rounded-xl border-2 border-r10-error/30 bg-r10-orange-100 p-5"
            >
                <R10Button
                    variant="danger"
                    data-test="delete-team-button"
                    @click="deleteDialogOpen = true"
                >
                    Kustuta tiim
                </R10Button>
            </div>
        </section>

        <InviteMemberModal
            v-if="permissions.canCreateInvitation"
            v-model:open="inviteDialogOpen"
            :team="team"
            :available-roles="availableRoles"
        />

        <RemoveMemberModal
            v-model:open="removeMemberDialogOpen"
            :team="team"
            :member="memberToRemove"
        />

        <CancelInvitationModal
            v-model:open="cancelInvitationDialogOpen"
            :team="team"
            :invitation="invitationToCancel"
        />

        <DeleteTeamModal
            v-if="permissions.canDeleteTeam"
            v-model:open="deleteDialogOpen"
            :team="team"
        />
    </R10Page>
</template>
