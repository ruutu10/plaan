<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AdminCreateTeamModal from '@/components/AdminCreateTeamModal.vue';
import AdminDeleteTeamModal from '@/components/AdminDeleteTeamModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { useResource } from '@/composables/useResource';
import { edit, index } from '@/routes/admin/teams';
import { index as teamsApi } from '@/routes/api/teams';
import type { ManagedTeam } from '@/types';

const createOpen = ref(false);
const deleteOpen = ref(false);
/** The team the delete dialog is asking about. */
const teamToDelete = ref<ManagedTeam | null>(null);

const http = useHttp();

const {
    data: teams,
    loadFailed,
    reload: reloadTeams,
} = useResource(async () => {
    const response = (await http.submit(teamsApi())) as {
        data: ManagedTeam[];
    };

    return response.data;
});

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

function openDelete(team: ManagedTeam): void {
    teamToDelete.value = team;
    deleteOpen.value = true;
}
</script>

<template>
    <Head title="Tiimid" />

    <R10Page>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <StepHeader
                eyebrow="Haldus"
                title="Tiimid"
                lead="Maja tiimid. Ava tiim, et muuta selle nime või liikmeid."
            />

            <R10Button
                data-test="create-team-button"
                @click="createOpen = true"
            >
                <Plus class="h-4 w-4" />
                Uus tiim
            </R10Button>
        </div>

        <R10Table
            :columns="[
                { label: 'Tiim' },
                { label: 'Liikmeid' },
                { label: 'Formaate' },
                { label: 'Tegevused', align: 'right', srOnly: true },
            ]"
            :rows="teams"
            :load-failed="loadFailed"
            row-test-id="team-row"
            skeleton-test-id="team-skeleton-row"
            empty-text="Ühtegi tiimi pole veel loodud. Lisa esimene nupuga „Uus tiim“."
            error-text="Tiimide laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: team }">
                <td class="px-5 py-4 align-top">
                    <span
                        class="font-r10-display text-base font-semibold text-r10-ink"
                    >
                        {{ team.name }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap tabular-nums">
                    {{ team.memberCount ?? 0 }}
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap tabular-nums">
                    {{ team.formatCount ?? 0 }}
                </td>
                <td class="px-5 py-4 text-right align-top">
                    <div class="inline-flex items-center justify-end gap-2">
                        <R10Button
                            variant="outline"
                            size="sm"
                            :href="edit(team.id).url"
                            data-test="team-edit-link"
                            class="px-4 py-2"
                        >
                            Muuda
                            <Pencil class="h-3.5 w-3.5" />
                        </R10Button>

                        <button
                            type="button"
                            title="Kustuta tiim"
                            data-test="delete-team-button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                            @click="openDelete(team)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span class="sr-only">Kustuta</span>
                        </button>
                    </div>
                </td>
            </template>
        </R10Table>

        <AdminCreateTeamModal
            v-model:open="createOpen"
            @created="reloadTeams"
        />

        <AdminDeleteTeamModal
            v-model:open="deleteOpen"
            :team="teamToDelete"
            @deleted="reloadTeams"
        />
    </R10Page>
</template>
