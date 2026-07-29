<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Eye, LogOut, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import CreateTeamModal from '@/components/CreateTeamModal.vue';
import LeaveTeamModal from '@/components/LeaveTeamModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { edit, index } from '@/routes/teams';
import type { Team } from '@/types';

defineProps<{ teams: Team[] }>();

const leaveTeamDialogOpen = ref(false);
const teamLeaving = ref<Team | null>(null);

/** An owner cannot walk out on their own team, and nobody leaves their personal one. */
const canLeaveTeam = (team: Team): boolean =>
    !team.isPersonal && team.role !== 'owner';

function openLeaveTeamDialog(team: Team): void {
    teamLeaving.value = team;
    leaveTeamDialogOpen.value = true;
}

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
</script>

<template>
    <Head title="Tiimid" />

    <R10Page>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <StepHeader
                eyebrow="Tiimid"
                title="Minu tiimid"
                lead="Tiimid, kuhu sa kuulud. Ava tiim, et näha selle liikmeid."
            />

            <CreateTeamModal>
                <R10Button data-test="teams-new-team-button">
                    <Plus class="h-4 w-4" />
                    Uus tiim
                </R10Button>
            </CreateTeamModal>
        </div>

        <R10Table
            :columns="[
                { label: 'Tiim' },
                { label: 'Roll' },
                { label: 'Tegevused', align: 'right', srOnly: true },
            ]"
            :rows="teams"
            row-test-id="team-row"
            empty-text="Sa ei kuulu veel ühtegi tiimi."
            error-text="Tiimide laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: team }">
                <td class="px-5 py-4 align-top">
                    <span
                        class="font-r10-display text-base font-semibold text-r10-ink"
                    >
                        {{ team.name }}
                    </span>
                    <R10Pill v-if="team.isPersonal" class="ml-2 align-middle">
                        Isiklik
                    </R10Pill>
                </td>
                <td class="px-5 py-4 align-top text-r10-grey-500">
                    {{ team.roleLabel }}
                </td>
                <td class="px-5 py-4 text-right align-top">
                    <div class="inline-flex items-center justify-end gap-2">
                        <R10Button
                            variant="outline"
                            size="sm"
                            :href="edit(team.slug).url"
                            :data-test="
                                team.role === 'member'
                                    ? 'team-view-button'
                                    : 'team-edit-button'
                            "
                            class="px-4 py-2"
                        >
                            {{ team.role === 'member' ? 'Vaata' : 'Muuda' }}
                            <Eye
                                v-if="team.role === 'member'"
                                class="h-3.5 w-3.5"
                            />
                            <Pencil v-else class="h-3.5 w-3.5" />
                        </R10Button>

                        <button
                            v-if="canLeaveTeam(team)"
                            type="button"
                            title="Lahku tiimist"
                            data-test="team-leave-button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                            @click="openLeaveTeamDialog(team)"
                        >
                            <LogOut class="h-3.5 w-3.5" />
                            <span class="sr-only">Lahku</span>
                        </button>
                    </div>
                </td>
            </template>
        </R10Table>

        <LeaveTeamModal
            v-model:open="leaveTeamDialogOpen"
            :team="teamLeaving"
        />
    </R10Page>
</template>
