<script setup lang="ts">
import { Head, Link, setLayoutProps, useHttp } from '@inertiajs/vue3';
import { ArrowLeft, UserPlus, X } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AdminAddMemberModal from '@/components/AdminAddMemberModal.vue';
import AdminRemoveMemberModal from '@/components/AdminRemoveMemberModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { edit, index } from '@/routes/admin/teams';
import { show as teamApi, update } from '@/routes/api/teams';
import { update as updateMember } from '@/routes/api/teams/members';
import type {
    BreadcrumbItem,
    ManagedTeam,
    ManagedTeamFormData,
    ManagedTeamMember,
    ManagedTeamPermissions,
    RoleOption,
    TeamRole,
} from '@/types';

type Props = {
    teamId: number;
};

const props = defineProps<Props>();

/** Null until the team lands, which is what the skeleton keys off. */
const team = ref<ManagedTeam | null>(null);
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
const loadFailed = ref(false);

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

/**
 * Name the team in the breadcrumbs once it is known — the page is rendered as a
 * shell, so the trail starts one crumb short.
 */
function nameTheTrail(name: string): void {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Tiimid', href: index() },
            { title: name, href: edit(props.teamId) },
        ],
    });
}

async function loadTeam(): Promise<void> {
    try {
        const response = (await loader.submit(teamApi(props.teamId))) as {
            data: ManagedTeam;
            roles: RoleOption[];
            permissions: ManagedTeamPermissions;
        };

        team.value = response.data;
        members.value = response.data.members ?? [];
        roles.value = response.roles;
        permissions.value = response.permissions;

        form.name = response.data.name;
        form.defaults();

        nameTheTrail(response.data.name);
    } catch {
        loadFailed.value = true;
    }
}

onMounted(loadTeam);

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

        void loadTeam();
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

    <div
        class="flex h-full flex-1 flex-col bg-r10-paper px-5 py-7 font-r10-body text-r10-grey-700 md:px-8 md:py-9"
    >
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
        <Link
            v-else-if="!permissions.canUpdate"
            :href="index()"
            class="inline-flex items-center gap-2 font-r10-body text-xs font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:text-r10-orange-700"
        >
            <ArrowLeft class="h-3.5 w-3.5" />
            Tagasi
        </Link>

        <form
            v-else
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
            @submit.prevent="save"
        >
            <div class="flex flex-col gap-1.5">
                <R10Input
                    v-model="form.name"
                    label="Nimi"
                    required
                    placeholder="Tiimi nimi"
                />
                <span
                    v-if="form.errors.name"
                    data-test="team-name-error"
                    class="text-xs font-medium text-r10-orange-700"
                >
                    {{ form.errors.name }}
                </span>
            </div>

            <div class="flex items-center gap-3">
                <R10Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="team-save-button"
                >
                    Salvesta
                </R10Button>

                <Link
                    :href="index()"
                    class="inline-flex items-center gap-2 font-r10-body text-xs font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:text-r10-orange-700"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    Tagasi
                </Link>
            </div>
        </form>

        <section class="mt-9 max-w-2xl">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="m-0 font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                    >
                        Liikmed
                    </h2>
                    <p class="mt-1 text-[15px] text-r10-grey-500">
                        Tiimi kuuluvad kasutajad. Omanikku ei saa eemaldada ega
                        tema rolli muuta.
                    </p>
                </div>

                <R10Button
                    v-if="team && permissions.canAddMember"
                    size="sm"
                    data-test="add-member-button"
                    @click="addMemberOpen = true"
                >
                    <UserPlus class="h-4 w-4" />
                    Lisa liige
                </R10Button>
            </div>

            <div
                class="overflow-x-auto rounded-xl border-2 border-r10-grey-200 bg-white"
            >
                <table class="w-full border-collapse text-left text-sm">
                    <thead class="border-b-2 border-r10-navy">
                        <tr
                            class="font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-navy uppercase"
                        >
                            <th class="px-5 py-3.5">Nimi</th>
                            <th class="px-5 py-3.5">Roll</th>
                            <th class="px-5 py-3.5 text-right">
                                <span class="sr-only">Tegevused</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="member in members"
                            :key="member.id"
                            data-test="team-member-row"
                            class="border-b border-r10-grey-200 transition-colors last:border-0 hover:bg-r10-grey-100"
                        >
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
                                <span
                                    v-if="
                                        member.isOwner ||
                                        !permissions.canUpdateMember
                                    "
                                    data-test="team-member-role-label"
                                    class="inline-block rounded-full border border-r10-grey-200 px-3 py-1 text-[11px] font-bold tracking-[0.08em] text-r10-grey-500 uppercase"
                                >
                                    {{ member.roleLabel }}
                                </span>
                                <select
                                    v-else
                                    :value="member.role"
                                    :disabled="roleForm.processing"
                                    data-test="team-member-role-select"
                                    class="rounded-lg border-2 border-r10-grey-200 bg-white px-3 py-2 font-r10-body text-[14px] text-r10-ink outline-none focus:border-r10-orange disabled:opacity-50"
                                    @change="
                                        changeRole(
                                            member,
                                            ($event.target as HTMLSelectElement)
                                                .value as TeamRole,
                                        )
                                    "
                                >
                                    <option
                                        v-for="role in roles"
                                        :key="role.value"
                                        :value="role.value"
                                    >
                                        {{ role.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-5 py-4 text-right align-top">
                                <button
                                    v-if="
                                        !member.isOwner &&
                                        permissions.canRemoveMember
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
                        </tr>

                        <!-- Nothing has arrived yet: stand-in rows the width of
                             the real ones. -->
                        <tr
                            v-for="row in team === null && !loadFailed ? 2 : 0"
                            :key="`member-skeleton-${row}`"
                            data-test="team-member-skeleton-row"
                            class="border-b border-r10-grey-200 last:border-0"
                        >
                            <td v-for="cell in 3" :key="cell" class="px-5 py-4">
                                <span
                                    class="block h-4 animate-pulse rounded-full bg-r10-grey-200"
                                    :class="cell === 1 ? 'w-40' : 'w-16'"
                                />
                            </td>
                        </tr>

                        <tr v-if="team !== null && members.length === 0">
                            <td
                                colspan="3"
                                class="px-5 py-10 text-center text-[15px] text-r10-grey-500"
                            >
                                Sellel tiimil pole ühtegi liiget.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
    </div>
</template>
