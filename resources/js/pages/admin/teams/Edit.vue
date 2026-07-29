<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { UserPlus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AdminAddMemberModal from '@/components/AdminAddMemberModal.vue';
import AdminRemoveMemberModal from '@/components/AdminRemoveMemberModal.vue';
import R10BackLink from '@/components/technical-plan/R10BackLink.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10SectionHeader from '@/components/technical-plan/R10SectionHeader.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { useResource } from '@/composables/useResource';
import { useTrailingCrumb } from '@/composables/useTrailingCrumb';
import { edit, index } from '@/routes/admin/teams';
import { show as teamApi, update } from '@/routes/api/teams';
import { update as updateMember } from '@/routes/api/teams/members';
import type {
    ManagedTeam,
    ManagedTeamFormData,
    ManagedTeamMember,
    ManagedTeamPermissions,
    RoleOption,
    TeamRole,
} from '@/types';

const props = defineProps<{ teamId: number }>();

/** Kept apart from the team: the member rows are written one at a time. */
const members = ref<ManagedTeamMember[]>([]);
const roles = ref<RoleOption[]>([]);
/** What this reader may write; nothing at all until the team lands. */
const permissions = ref<ManagedTeamPermissions>({
    canUpdate: false,
    canAddMember: false,
    canUpdateMember: false,
    canRemoveMember: false,
});

const addMemberOpen = ref(false);
const removeMemberOpen = ref(false);
/** The member the remove dialog is asking about. */
const memberToRemove = ref<ManagedTeamMember | null>(null);

const loader = useHttp();

const form = useHttp<ManagedTeamFormData>({ name: '' });

const roleForm = useHttp<{ role: TeamRole }>({ role: 'member' });

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tiimid',
                href: index(),
            },
        ],
    },
});

const nameTheTrail = useTrailingCrumb(
    { title: 'Tiimid', href: index() },
    edit(props.teamId),
);

const {
    data: team,
    loadFailed,
    reload: reloadTeam,
} = useResource(async () => {
    const response = (await loader.submit(teamApi(props.teamId))) as {
        data: ManagedTeam;
        roles: RoleOption[];
        permissions: ManagedTeamPermissions;
    };

    members.value = response.data.members ?? [];
    roles.value = response.roles;
    permissions.value = response.permissions;

    form.name = response.data.name;
    form.defaults();

    nameTheTrail(response.data.name);

    return response.data;
});

/** Null while the team is still on its way, which is what the skeleton keys off. */
const memberRows = computed(() => (team.value === null ? null : members.value));

const roleOptions = computed(() =>
    roles.value.map((role) => ({ value: role.value, label: role.label })),
);

async function save(): Promise<void> {
    try {
        const { data } = (await form.submit(update(props.teamId))) as {
            data: ManagedTeam;
        };

        team.value = { ...data, members: undefined };
        form.defaults();

        nameTheTrail(data.name);

        toast.success('Tiim salvestatud.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Salvestamine ebaõnnestus. Proovi uuesti.');
        }
    }
}

/**
 * Write one member's role. The row is replaced with what the server answers
 * rather than with what was picked, and a refused change is put back by
 * reading the team afresh.
 */
async function changeRole(
    member: ManagedTeamMember,
    role: TeamRole,
): Promise<void> {
    roleForm.role = role;

    try {
        const { data } = (await roleForm.submit(
            updateMember([props.teamId, member.id]),
        )) as { data: ManagedTeamMember };

        members.value = members.value.map((row) =>
            row.id === data.id ? data : row,
        );

        toast.success('Roll muudetud.');
    } catch {
        toast.error('Rolli muutmine ebaõnnestus. Proovi uuesti.');

        reloadTeam();
    }
}

function openRemoveMember(member: ManagedTeamMember): void {
    memberToRemove.value = member;
    removeMemberOpen.value = true;
}

function addMember(member: ManagedTeamMember): void {
    members.value = [...members.value, member].sort((a, b) =>
        a.name.localeCompare(b.name),
    );
}

function forgetMember(): void {
    members.value = members.value.filter(
        (row) => row.id !== memberToRemove.value?.id,
    );
}
</script>

<template>
    <Head :title="team?.name ?? 'Tiim'" />

    <R10Page>
        <StepHeader
            eyebrow="Haldus"
            :title="team?.name ?? 'Tiim'"
            lead="Muuda tiimi nime ja seda, kes tiimi kuuluvad."
        />

        <p
            v-if="loadFailed"
            class="max-w-2xl rounded-xl border-2 border-r10-grey-200 bg-white p-5 text-[15px] text-r10-orange-700 md:p-7"
        >
            Tiimi laadimine ebaõnnestus. Proovi lehte värskendada.
        </p>

        <!-- Nothing has arrived yet: a stand-in the shape of the name field. -->
        <div
            v-else-if="team === null"
            data-test="team-form-skeleton"
            class="flex max-w-2xl flex-col gap-2 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
        >
            <span
                class="block h-3 w-24 animate-pulse rounded-full bg-r10-grey-200"
            />
            <span class="block h-12 animate-pulse rounded-lg bg-r10-grey-100" />
        </div>

        <!-- A reader who may not rename the team is shown no field to do it
             in; the header already carries the name. -->
        <R10BackLink v-else-if="!permissions.canUpdate" :href="index()" />

        <form
            v-else
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
            @submit.prevent="save"
        >
            <R10Input
                v-model="form.name"
                label="Nimi"
                required
                placeholder="Tiimi nimi"
                :error="form.errors.name"
                error-test-id="team-name-error"
            />

            <div class="flex items-center gap-3">
                <R10Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="team-save-button"
                >
                    Salvesta
                </R10Button>

                <R10BackLink :href="index()" />
            </div>
        </form>

        <section class="mt-9 max-w-2xl">
            <R10SectionHeader
                title="Liikmed"
                lead="Tiimi kuuluvad kasutajad. Omanikku ei saa eemaldada ega tema rolli muuta."
                class="mb-4"
            >
                <template #action>
                    <R10Button
                        v-if="team && permissions.canAddMember"
                        size="sm"
                        data-test="add-member-button"
                        @click="addMemberOpen = true"
                    >
                        <UserPlus class="h-4 w-4" />
                        Lisa liige
                    </R10Button>
                </template>
            </R10SectionHeader>

            <R10Table
                :columns="[
                    { label: 'Nimi' },
                    { label: 'Roll' },
                    { label: 'Tegevused', align: 'right', srOnly: true },
                ]"
                :rows="memberRows"
                :load-failed="loadFailed"
                :skeleton-rows="2"
                :skeleton-widths="['w-40', 'w-16']"
                row-test-id="team-member-row"
                skeleton-test-id="team-member-skeleton-row"
                empty-text="Sellel tiimil pole ühtegi liiget."
                error-text="Tiimi laadimine ebaõnnestus. Proovi lehte värskendada."
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
                        <R10Pill
                            v-if="
                                member.isOwner || !permissions.canUpdateMember
                            "
                            size="md"
                            data-test="team-member-role-label"
                        >
                            {{ member.roleLabel }}
                        </R10Pill>
                        <R10Select
                            v-else
                            :model-value="member.role"
                            :options="roleOptions"
                            :disabled="roleForm.processing"
                            data-test="team-member-role-select"
                            @update:model-value="
                                changeRole(member, $event as TeamRole)
                            "
                        />
                    </td>
                    <td class="px-5 py-4 text-right align-top">
                        <button
                            v-if="
                                !member.isOwner && permissions.canRemoveMember
                            "
                            type="button"
                            title="Eemalda liige"
                            data-test="remove-member-button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                            @click="openRemoveMember(member)"
                        >
                            <X class="h-3.5 w-3.5" />
                            <span class="sr-only">Eemalda</span>
                        </button>
                    </td>
                </template>
            </R10Table>
        </section>

        <AdminAddMemberModal
            v-model:open="addMemberOpen"
            :team-id="teamId"
            :roles="roles"
            @added="addMember"
        />

        <AdminRemoveMemberModal
            v-model:open="removeMemberOpen"
            :team-id="teamId"
            :member="memberToRemove"
            @removed="forgetMember"
        />
    </R10Page>
</template>
