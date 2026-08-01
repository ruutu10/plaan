<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { FileClock, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import DeletePerformanceModal from '@/components/DeletePerformanceModal.vue';
import PerformanceModal from '@/components/PerformanceModal.vue';
import ShowFormFields from '@/components/ShowFormFields.vue';
import R10BackLink from '@/components/technical-plan/R10BackLink.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10SectionHeader from '@/components/technical-plan/R10SectionHeader.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { useResource } from '@/composables/useResource';
import { useTrailingCrumb } from '@/composables/useTrailingCrumb';
import { formatEstonianDate } from '@/lib/date';
import { show as showApi, update } from '@/routes/api/shows';
import { index as performancesApi } from '@/routes/api/shows/performances';
import { edit, index } from '@/routes/shows';
import type { Performance, Show, ShowFormData, ShowTeamOption } from '@/types';

const props = defineProps<{ showId: number }>();

const teams = ref<ShowTeamOption[]>([]);
/** The groups a performance may be handed to; wider than the show's own. */
const performanceTeams = ref<ShowTeamOption[]>([]);

/**
 * Whether the user may correct the show itself. A group that only plays a
 * performance on this evening reaches the page to correct its own slot and
 * finds the show's own details read-only.
 */
const canEditShow = ref(true);

const performanceModalOpen = ref(false);
const deleteModalOpen = ref(false);
/** The performance a modal is working on; null in the add-a-performance case. */
const chosenPerformance = ref<Performance | null>(null);

const loader = useHttp();
const performanceLoader = useHttp();

const form = useHttp<ShowFormData>({
    team_id: null,
    name: '',
    description: '',
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

const nameTheTrail = useTrailingCrumb(
    { title: 'Lavastused', href: index() },
    edit(props.showId),
);

// The show and its performances are separate resources, so they are fetched
// side by side rather than one after the other.
const { data: show, loadFailed } = useResource(async () => {
    const response = (await loader.submit(showApi(props.showId))) as {
        data: Show;
        teams: ShowTeamOption[];
    };

    teams.value = response.teams;
    canEditShow.value = response.data.canEdit;

    form.team_id = response.data.teamId;
    form.name = response.data.name;
    form.description = response.data.description ?? '';
    form.defaults();

    nameTheTrail(response.data.name);

    return response.data;
});

const {
    data: performances,
    loadFailed: performancesFailed,
    reload: reloadPerformances,
} = useResource(async () => {
    const response = (await performanceLoader.submit(
        performancesApi(props.showId),
    )) as { data: Performance[]; teams: ShowTeamOption[] };

    performanceTeams.value = response.teams;

    return response.data;
});

function openAddPerformance(): void {
    chosenPerformance.value = null;
    performanceModalOpen.value = true;
}

function openEditPerformance(performance: Performance): void {
    chosenPerformance.value = performance;
    performanceModalOpen.value = true;
}

function openDeletePerformance(performance: Performance): void {
    chosenPerformance.value = performance;
    deleteModalOpen.value = true;
}

async function save(): Promise<void> {
    try {
        const { data } = (await form.submit(update(props.showId))) as {
            data: Show;
        };

        show.value = data;
        form.defaults();

        nameTheTrail(data.name);

        toast.success('Lavastus salvestatud.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Salvestamine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <Head :title="show?.name ?? 'Lavastus'" />

    <R10Page>
        <StepHeader
            eyebrow="Haldus"
            :title="show?.name ?? 'Lavastus'"
            lead="Muuda lavastuse andmeid. Muudatused kehtivad kõigile selle lavastuse etendustele."
        />

        <p
            v-if="loadFailed"
            class="max-w-2xl rounded-xl border-2 border-r10-grey-200 bg-white p-5 text-[15px] text-r10-orange-700 md:p-7"
        >
            Lavastuse laadimine ebaõnnestus. Proovi lehte värskendada.
        </p>

        <!-- Nothing has arrived yet: stand-ins the shape of the three fields. -->
        <div
            v-else-if="show === null"
            data-test="show-form-skeleton"
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
        >
            <div v-for="field in 3" :key="field" class="flex flex-col gap-2">
                <span
                    class="block h-3 w-24 animate-pulse rounded-full bg-r10-grey-200"
                />
                <span
                    class="block animate-pulse rounded-lg bg-r10-grey-100"
                    :class="field === 3 ? 'h-32' : 'h-12'"
                />
            </div>
        </div>

        <form
            v-else
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
            @submit.prevent="save"
        >
            <p
                v-if="!canEditShow"
                data-test="show-read-only-notice"
                class="rounded-lg border-2 border-r10-grey-200 bg-r10-grey-100 px-4 py-3 text-[13px] text-r10-grey-700"
            >
                See lavastus kuulub teisele tiimile. Sa saad muuta ainult oma
                tiimi etteastet allpool.
            </p>

            <ShowFormFields
                v-model:team-id="form.team_id"
                v-model:name="form.name"
                v-model:description="form.description"
                :teams="teams"
                :errors="form.errors"
                :disabled="!canEditShow"
            />

            <div class="flex items-center justify-between gap-3">
                <R10BackLink :href="index()" />

                <R10Button
                    v-if="canEditShow"
                    type="submit"
                    :disabled="form.processing"
                    data-test="show-save-button"
                >
                    Salvesta
                </R10Button>
            </div>
        </form>

        <section class="mt-9 max-w-2xl">
            <R10SectionHeader
                title="Etendused"
                lead="Selle lavastuse kuupäevad. Iga kuupäeva külge käib eraldi tehnikaplaan."
                class="mb-4"
            >
                <template #action>
                    <R10Button
                        v-if="canEditShow"
                        size="sm"
                        data-test="add-performance-button"
                        @click="openAddPerformance"
                    >
                        <Plus class="h-4 w-4" />
                        Lisa etendus
                    </R10Button>
                </template>
            </R10SectionHeader>

            <R10Table
                :columns="[
                    { label: 'Kuupäev ja algus' },
                    { label: 'Etteaste' },
                    { label: 'Kestus' },
                    { label: 'Olek' },
                    { label: 'Tehnikaplaane' },
                    { label: 'Tegevused', align: 'right', srOnly: true },
                ]"
                :rows="performances"
                :load-failed="performancesFailed"
                :skeleton-rows="2"
                :skeleton-widths="['w-24', 'w-12']"
                row-test-id="performance-row"
                skeleton-test-id="performance-skeleton-row"
                empty-text="Sellel lavastusel pole veel ühtegi etendust."
                error-text="Etenduste laadimine ebaõnnestus. Proovi lehte värskendada."
            >
                <template #row="{ row: performance }">
                    <td
                        class="px-5 py-4 font-medium whitespace-nowrap text-r10-ink"
                    >
                        {{ formatEstonianDate(performance.date) }}
                        <span class="text-r10-grey-500">
                            {{ performance.startTime }}
                        </span>
                    </td>
                    <td class="px-5 py-4 align-top">
                        <span
                            v-if="performance.title"
                            class="block text-r10-ink"
                            data-test="performance-title"
                        >
                            {{ performance.title }}
                        </span>
                        <span
                            class="block text-r10-grey-500"
                            data-test="performance-team"
                        >
                            {{ performance.teamName ?? show?.teamName ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap">
                        {{
                            performance.duration
                                ? `${performance.duration} min`
                                : '—'
                        }}
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap">
                        <R10Pill
                            v-if="performance.isDraft"
                            tone="accent"
                            data-test="performance-draft-badge"
                            title="Ülevaatamata etendust ei pakuta tehnikaplaani koostajale."
                            class="border-transparent"
                        >
                            <FileClock class="h-3.5 w-3.5" />
                            Ülevaatamata
                        </R10Pill>
                        <span
                            v-else
                            class="font-r10-body text-[11px] font-bold tracking-[0.08em] text-r10-grey-500 uppercase"
                        >
                            Kinnitatud
                        </span>
                    </td>
                    <td class="px-5 py-4 tabular-nums">
                        {{ performance.technicalPlanCount ?? 0 }}
                    </td>
                    <td class="px-5 py-4 text-right whitespace-nowrap">
                        <div class="inline-flex items-center justify-end gap-2">
                            <button
                                type="button"
                                title="Muuda etendust"
                                data-test="edit-performance-button"
                                class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-navy bg-white p-2 text-r10-navy transition hover:bg-r10-navy hover:text-white"
                                @click="openEditPerformance(performance)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                <span class="sr-only">Muuda</span>
                            </button>

                            <button
                                type="button"
                                title="Kustuta etendus"
                                data-test="delete-performance-button"
                                class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                                @click="openDeletePerformance(performance)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span class="sr-only">Kustuta</span>
                            </button>
                        </div>
                    </td>
                </template>
            </R10Table>
        </section>

        <PerformanceModal
            v-model:open="performanceModalOpen"
            :show-id="showId"
            :performance="chosenPerformance"
            :teams="performanceTeams"
            @saved="reloadPerformances"
        />

        <DeletePerformanceModal
            v-model:open="deleteModalOpen"
            :show-id="showId"
            :performance="chosenPerformance"
            @deleted="reloadPerformances"
        />
    </R10Page>
</template>
