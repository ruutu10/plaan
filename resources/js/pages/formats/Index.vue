<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Head, useHttp } from '@inertiajs/vue3';
import { Pencil, Plus, Sparkles, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ClaudeReasoningLogModal from '@/components/ClaudeReasoningLogModal.vue';
import CreateFormatModal from '@/components/CreateFormatModal.vue';
import DeleteFormatModal from '@/components/DeleteFormatModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { useResource } from '@/composables/useResource';
import {
    claudeLogs as reasoningLogsApi,
    index as formatsApi,
} from '@/routes/api/formats';
import { edit, index } from '@/routes/formats';
import type { Format, FormatTeamOption } from '@/types';

const teams = ref<FormatTeamOption[]>([]);
const createOpen = ref(false);
const deleteOpen = ref(false);
/** The format the delete dialog is asking about. */
const formatToDelete = ref<Format | null>(null);
/** Where the log dialog reads from; null until a button is pressed. */
const chosenLogSource = ref<UrlMethodPair | null>(null);
const logOpen = ref(false);

const http = useHttp();

const {
    data: formats,
    loadFailed,
    reload: reloadFormats,
} = useResource(async () => {
    const response = (await http.submit(formatsApi())) as {
        data: Format[];
        teams: FormatTeamOption[];
    };

    teams.value = response.teams;

    return response.data;
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Formaadid',
                href: index(),
            },
        ],
    },
});

function openDelete(format: Format): void {
    formatToDelete.value = format;
    deleteOpen.value = true;
}

function openReasoningLog(format: Format): void {
    chosenLogSource.value = reasoningLogsApi(format.id);
    logOpen.value = true;
}
</script>

<template>
    <Head title="Formaadid" />

    <R10Page>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <StepHeader
                eyebrow="Haldus"
                title="Formaadid"
                lead="Sinu tiimide formaadid. Ava formaat, et muuta selle nime, kirjeldust või omanikku."
            />

            <R10Button
                data-test="create-format-button"
                :disabled="teams.length === 0"
                @click="createOpen = true"
            >
                <Plus class="h-4 w-4" />
                Uus formaat
            </R10Button>
        </div>

        <R10Table
            :columns="[
                { label: 'Formaat' },
                { label: 'Tiim' },
                { label: 'Etendusi' },
                { label: 'Tegevused', align: 'right', srOnly: true },
            ]"
            :rows="formats"
            :load-failed="loadFailed"
            row-test-id="format-row"
            skeleton-test-id="format-skeleton-row"
            empty-text="Ühtegi formaati pole veel sisestatud. Lisa esimene nupuga „Uus formaat“."
            error-text="Formaatide laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: format }">
                <td class="px-5 py-4 align-top">
                    <span
                        class="font-r10-display text-base font-semibold text-r10-ink"
                    >
                        {{ format.name }}
                    </span>
                    <span
                        v-if="format.description"
                        class="mt-0.5 line-clamp-2 block max-w-prose text-[13px] text-r10-grey-500"
                    >
                        {{ format.description }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top text-r10-grey-500">
                    {{ format.teamName ?? '—' }}
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap tabular-nums">
                    {{ format.performanceCount ?? 0 }}
                </td>
                <td class="px-5 py-4 text-right align-top">
                    <div class="inline-flex items-center justify-end gap-2">
                        <R10Button
                            variant="outline"
                            size="sm"
                            :href="edit(format.id).url"
                            data-test="format-edit-link"
                            class="px-4 py-2"
                        >
                            <!-- A format reached only because one of the user's
                                 groups plays an act on it opens read-only,
                                 apart from that act. -->
                            {{ format.canEdit ? 'Muuda' : 'Vaata' }}
                            <Pencil class="h-3.5 w-3.5" />
                        </R10Button>

                        <!-- Only shown to a user the server told there is
                             something to read; everyone else is sent zero. -->
                        <button
                            v-if="format.reasoningLogCount > 0"
                            type="button"
                            title="Vaata impordi põhjendusi"
                            data-test="format-reasoning-log-button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-navy hover:text-r10-navy"
                            @click="openReasoningLog(format)"
                        >
                            <Sparkles class="h-3.5 w-3.5" />
                            <span class="sr-only">Põhjendused</span>
                        </button>

                        <button
                            v-if="format.canEdit"
                            type="button"
                            title="Kustuta formaat"
                            data-test="delete-format-button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                            @click="openDelete(format)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span class="sr-only">Kustuta</span>
                        </button>
                    </div>
                </td>
            </template>
        </R10Table>

        <CreateFormatModal
            v-model:open="createOpen"
            :teams="teams"
            @created="reloadFormats"
        />

        <DeleteFormatModal
            v-model:open="deleteOpen"
            :format="formatToDelete"
            @deleted="reloadFormats"
        />

        <ClaudeReasoningLogModal
            v-model:open="logOpen"
            :source="chosenLogSource"
        />
    </R10Page>
</template>
