<script setup lang="ts">
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import AdminCreateTeamModal from '@/components/AdminCreateTeamModal.vue';
import AdminDeleteTeamModal from '@/components/AdminDeleteTeamModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { edit, index } from '@/routes/admin/teams';
import { index as teamsApi } from '@/routes/api/teams';
import type { ManagedTeam } from '@/types';

/** Null until the first response lands, which is what the skeleton keys off. */
const teams = ref<ManagedTeam[] | null>(null);
const loadFailed = ref(false);
const createOpen = ref(false);
const deleteOpen = ref(false);
/** The team the delete dialog is asking about. */
const teamToDelete = ref<ManagedTeam | null>(null);

const http = useHttp();

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

async function loadTeams(): Promise<void> {
    try {
        const response = (await http.submit(teamsApi())) as {
            data: ManagedTeam[];
        };

        teams.value = response.data;
    } catch {
        loadFailed.value = true;
    }
}

onMounted(loadTeams);

/**
 * Fetch the listing afresh rather than splicing the row in or out: the server
 * decides both the order and the counts, and they have just moved.
 */
function reloadTeams(): void {
    void loadTeams();
}

function openDelete(team: ManagedTeam): void {
    teamToDelete.value = team;
    deleteOpen.value = true;
}
</script>

<template>
    <Head title="Tiimid" />

    <div
        class="flex h-full flex-1 flex-col bg-r10-paper px-5 py-7 font-r10-body text-r10-grey-700 md:px-8 md:py-9"
    >
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

        <div
            class="overflow-x-auto rounded-xl border-2 border-r10-grey-200 bg-white"
        >
            <table class="w-full border-collapse text-left text-sm">
                <thead class="border-b-2 border-r10-navy">
                    <tr
                        class="font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-navy uppercase"
                    >
                        <th class="px-5 py-3.5">Tiim</th>
                        <th class="px-5 py-3.5">Liikmeid</th>
                        <th class="px-5 py-3.5">Lavastusi</th>
                        <th class="px-5 py-3.5 text-right">
                            <span class="sr-only">Tegevused</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="team in teams ?? []"
                        :key="team.id"
                        data-test="team-row"
                        class="border-b border-r10-grey-200 transition-colors last:border-0 hover:bg-r10-grey-100"
                    >
                        <td class="px-5 py-4 align-top">
                            <span
                                class="font-r10-display text-base font-semibold text-r10-ink"
                            >
                                {{ team.name }}
                            </span>
                            <span
                                v-if="team.isPersonal"
                                data-test="team-personal-badge"
                                class="ml-2 inline-block rounded-full border border-r10-grey-200 px-2 py-0.5 align-middle text-[11px] font-bold tracking-[0.08em] text-r10-grey-500 uppercase"
                            >
                                Isiklik
                            </span>
                        </td>
                        <td
                            class="px-5 py-4 align-top whitespace-nowrap tabular-nums"
                        >
                            {{ team.memberCount ?? 0 }}
                        </td>
                        <td
                            class="px-5 py-4 align-top whitespace-nowrap tabular-nums"
                        >
                            {{ team.showCount ?? 0 }}
                        </td>
                        <td class="px-5 py-4 text-right align-top">
                            <div
                                class="inline-flex items-center justify-end gap-2"
                            >
                                <Link
                                    :href="edit(team.id)"
                                    data-test="team-edit-link"
                                    class="inline-flex items-center gap-2 rounded-full border-2 border-r10-navy bg-white px-4 py-2 font-r10-body text-xs font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:bg-r10-navy hover:text-white"
                                >
                                    Muuda
                                    <Pencil class="h-3.5 w-3.5" />
                                </Link>

                                <!-- A personal team is nobody's to delete: it
                                     is where members are put back when the
                                     groups they joined go away. -->
                                <button
                                    v-if="!team.isPersonal"
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
                    </tr>

                    <!-- Nothing has arrived yet: stand-in rows the width of the
                         real ones, so the table does not jump once it does. -->
                    <tr
                        v-for="row in teams === null && !loadFailed ? 3 : 0"
                        :key="`skeleton-${row}`"
                        data-test="team-skeleton-row"
                        class="border-b border-r10-grey-200 last:border-0"
                    >
                        <td v-for="cell in 4" :key="cell" class="px-5 py-4">
                            <span
                                class="block h-4 animate-pulse rounded-full bg-r10-grey-200"
                                :class="cell === 1 ? 'w-48' : 'w-16'"
                            />
                        </td>
                    </tr>

                    <tr v-if="loadFailed">
                        <td
                            colspan="4"
                            class="px-5 py-12 text-center text-[15px] text-r10-orange-700"
                        >
                            Tiimide laadimine ebaõnnestus. Proovi lehte
                            värskendada.
                        </td>
                    </tr>

                    <tr v-else-if="teams?.length === 0">
                        <td
                            colspan="4"
                            class="px-5 py-12 text-center text-[15px] text-r10-grey-500"
                        >
                            Ühtegi tiimi pole veel loodud. Lisa esimene nupuga
                            „Uus tiim“.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AdminCreateTeamModal
            v-model:open="createOpen"
            @created="reloadTeams"
        />

        <AdminDeleteTeamModal
            v-model:open="deleteOpen"
            :team="teamToDelete"
            @deleted="reloadTeams"
        />
    </div>
</template>
