<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Head, useHttp } from '@inertiajs/vue3';
import { Pencil, Plus, Sparkles, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ClaudeReasoningLogModal from '@/components/ClaudeReasoningLogModal.vue';
import CreateShowModal from '@/components/CreateShowModal.vue';
import DeleteShowModal from '@/components/DeleteShowModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { useResource } from '@/composables/useResource';
import {
    claudeLogs as reasoningLogsApi,
    index as showsApi,
} from '@/routes/api/shows';
import { edit, index } from '@/routes/shows';
import type { Show, ShowTeamOption } from '@/types';

const teams = ref<ShowTeamOption[]>([]);
const createOpen = ref(false);
const deleteOpen = ref(false);
/** The show the delete dialog is asking about. */
const showToDelete = ref<Show | null>(null);
/** Where the log dialog reads from; null until a button is pressed. */
const chosenLogSource = ref<UrlMethodPair | null>(null);
const logOpen = ref(false);

const http = useHttp();

const {
    data: shows,
    loadFailed,
    reload: reloadShows,
} = useResource(async () => {
    const response = (await http.submit(showsApi())) as {
        data: Show[];
        teams: ShowTeamOption[];
    };

    teams.value = response.teams;

    return response.data;
});

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

function openDelete(show: Show): void {
    showToDelete.value = show;
    deleteOpen.value = true;
}

function openReasoningLog(show: Show): void {
    chosenLogSource.value = reasoningLogsApi(show.id);
    logOpen.value = true;
}
</script>

<template>
    <Head title="Lavastused" />

    <R10Page>
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

        <R10Table
            :columns="[
                { label: 'Lavastus' },
                { label: 'Tiim' },
                { label: 'Etendusi' },
                { label: 'Tegevused', align: 'right', srOnly: true },
            ]"
            :rows="shows"
            :load-failed="loadFailed"
            row-test-id="show-row"
            skeleton-test-id="show-skeleton-row"
            empty-text="Ühtegi lavastust pole veel sisestatud. Lisa esimene nupuga „Uus lavastus“."
            error-text="Lavastuste laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: show }">
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
                <td class="px-5 py-4 align-top whitespace-nowrap tabular-nums">
                    {{ show.performanceCount ?? 0 }}
                </td>
                <td class="px-5 py-4 text-right align-top">
                    <div class="inline-flex items-center justify-end gap-2">
                        <R10Button
                            variant="outline"
                            size="sm"
                            :href="edit(show.id).url"
                            data-test="show-edit-link"
                            class="px-4 py-2"
                        >
                            <!-- A show reached only because one of the user's
                                 groups plays an act on it opens read-only,
                                 apart from that act. -->
                            {{ show.canEdit ? 'Muuda' : 'Vaata' }}
                            <Pencil class="h-3.5 w-3.5" />
                        </R10Button>

                        <!-- Only shown to a user the server told there is
                             something to read; everyone else is sent zero. -->
                        <button
                            v-if="show.reasoningLogCount > 0"
                            type="button"
                            title="Vaata impordi põhjendusi"
                            data-test="show-reasoning-log-button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-navy hover:text-r10-navy"
                            @click="openReasoningLog(show)"
                        >
                            <Sparkles class="h-3.5 w-3.5" />
                            <span class="sr-only">Põhjendused</span>
                        </button>

                        <button
                            v-if="show.canEdit"
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
            </template>
        </R10Table>

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

        <ClaudeReasoningLogModal
            v-model:open="logOpen"
            :source="chosenLogSource"
        />
    </R10Page>
</template>
