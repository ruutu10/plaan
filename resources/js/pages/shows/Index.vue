<script setup lang="ts">
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import CreateShowModal from '@/components/CreateShowModal.vue';
import DeleteShowModal from '@/components/DeleteShowModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { index as showsApi } from '@/routes/api/shows';
import { edit, index } from '@/routes/shows';
import type { Show, ShowTeamOption } from '@/types';

/** Null until the first response lands, which is what the skeleton keys off. */
const shows = ref<Show[] | null>(null);
const teams = ref<ShowTeamOption[]>([]);
const loadFailed = ref(false);
const createOpen = ref(false);
const deleteOpen = ref(false);
/** The show the delete dialog is asking about. */
const showToDelete = ref<Show | null>(null);

const http = useHttp();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Lavastused',
                href: index(),
            },
        ],
    },
});

async function loadShows(): Promise<void> {
    try {
        const response = (await http.submit(showsApi())) as {
            data: Show[];
            teams: ShowTeamOption[];
        };

        shows.value = response.data;
        teams.value = response.teams;
    } catch {
        loadFailed.value = true;
    }
}

onMounted(loadShows);

/**
 * Fetch the listing afresh rather than splicing the row in or out: the server
 * decides both the order and the performance count, and it has just moved.
 */
function reloadShows(): void {
    void loadShows();
}

function openDelete(show: Show): void {
    showToDelete.value = show;
    deleteOpen.value = true;
}
</script>

<template>
    <Head title="Lavastused" />

    <div
        class="flex h-full flex-1 flex-col bg-r10-paper px-5 py-7 font-r10-body text-r10-grey-700 md:px-8 md:py-9"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <StepHeader
                eyebrow="Haldus"
                title="Lavastused"
                lead="Sinu tiimide lavastused. Ava lavastus, et muuta selle nime, kirjeldust või omanikku."
            />

            <R10Button
                data-test="create-show-button"
                :disabled="teams.length === 0"
                @click="createOpen = true"
            >
                <Plus class="h-4 w-4" />
                Uus lavastus
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
                        <th class="px-5 py-3.5">Lavastus</th>
                        <th class="px-5 py-3.5">Tiim</th>
                        <th class="px-5 py-3.5">Etendusi</th>
                        <th class="px-5 py-3.5 text-right">
                            <span class="sr-only">Tegevused</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="show in shows ?? []"
                        :key="show.id"
                        data-test="show-row"
                        class="border-b border-r10-grey-200 transition-colors last:border-0 hover:bg-r10-grey-100"
                    >
                        <td class="px-5 py-4 align-top">
                            <span
                                class="font-r10-display text-base font-semibold text-r10-ink"
                            >
                                {{ show.name }}
                            </span>
                            <span
                                v-if="show.description"
                                class="mt-0.5 line-clamp-2 block max-w-prose text-[13px] text-r10-grey-500"
                            >
                                {{ show.description }}
                            </span>
                        </td>
                        <td class="px-5 py-4 align-top text-r10-grey-500">
                            {{ show.teamName ?? '—' }}
                        </td>
                        <td
                            class="px-5 py-4 align-top whitespace-nowrap tabular-nums"
                        >
                            {{ show.performanceCount ?? 0 }}
                        </td>
                        <td class="px-5 py-4 text-right align-top">
                            <div
                                class="inline-flex items-center justify-end gap-2"
                            >
                                <Link
                                    :href="edit(show.id)"
                                    data-test="show-edit-link"
                                    class="inline-flex items-center gap-2 rounded-full border-2 border-r10-navy bg-white px-4 py-2 font-r10-body text-xs font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:bg-r10-navy hover:text-white"
                                >
                                    Muuda
                                    <Pencil class="h-3.5 w-3.5" />
                                </Link>

                                <button
                                    type="button"
                                    title="Kustuta lavastus"
                                    data-test="delete-show-button"
                                    class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                                    @click="openDelete(show)"
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
                        v-for="row in shows === null && !loadFailed ? 3 : 0"
                        :key="`skeleton-${row}`"
                        data-test="show-skeleton-row"
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
                            Lavastuste laadimine ebaõnnestus. Proovi lehte
                            värskendada.
                        </td>
                    </tr>

                    <tr v-else-if="shows?.length === 0">
                        <td
                            colspan="4"
                            class="px-5 py-12 text-center text-[15px] text-r10-grey-500"
                        >
                            Ühtegi lavastust pole veel sisestatud. Lisa esimene
                            nupuga „Uus lavastus“.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <CreateShowModal
            v-model:open="createOpen"
            :teams="teams"
            @created="reloadShows"
        />

        <DeleteShowModal
            v-model:open="deleteOpen"
            :show="showToDelete"
            @deleted="reloadShows"
        />
    </div>
</template>
