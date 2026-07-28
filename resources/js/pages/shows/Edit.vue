<script setup lang="ts">
import { Head, Link, setLayoutProps, useHttp } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Trash2 } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import DeletePerformanceModal from '@/components/DeletePerformanceModal.vue';
import PerformanceModal from '@/components/PerformanceModal.vue';
import ShowFormFields from '@/components/ShowFormFields.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { formatEstonianDate } from '@/lib/date';
import { show as showApi, update } from '@/routes/api/shows';
import { index as performancesApi } from '@/routes/api/shows/performances';
import { edit, index } from '@/routes/shows';
import type {
    BreadcrumbItem,
    Performance,
    Show,
    ShowFormData,
    ShowTeamOption,
} from '@/types';

type Props = {
    showId: number;
};

const props = defineProps<Props>();

/** Null until the show lands, which is what the skeleton keys off. */
const show = ref<Show | null>(null);
const teams = ref<ShowTeamOption[]>([]);
const loadFailed = ref(false);

/** Likewise null until the performances land; a show may legitimately have none. */
const performances = ref<Performance[] | null>(null);
const performancesFailed = ref(false);

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

/**
 * Name the show in the breadcrumbs once it is known — the page is rendered as a
 * shell, so the trail starts one crumb short.
 */
function nameTheTrail(name: string): void {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Lavastused', href: index() },
            { title: name, href: edit(props.showId) },
        ],
    });
}

async function loadShow(): Promise<void> {
    try {
        const response = (await loader.submit(showApi(props.showId))) as {
            data: Show;
            teams: ShowTeamOption[];
        };

        show.value = response.data;
        teams.value = response.teams;

        form.team_id = response.data.teamId;
        form.name = response.data.name;
        form.description = response.data.description ?? '';
        form.defaults();

        nameTheTrail(response.data.name);
    } catch {
        loadFailed.value = true;
    }
}

async function loadPerformances(): Promise<void> {
    try {
        const { data } = (await performanceLoader.submit(
            performancesApi(props.showId),
        )) as { data: Performance[] };

        performances.value = data;
    } catch {
        performancesFailed.value = true;
    }
}

// The show and its performances are separate resources, so they are fetched side by
// side rather than one after the other.
onMounted(() => {
    void loadShow();
    void loadPerformances();
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

/**
 * Fetch the performances afresh after any change: the server decides their order,
 * and a saved date may have moved the row somewhere else in it.
 */
function reloadPerformances(): void {
    void loadPerformances();
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

    <div
        class="flex h-full flex-1 flex-col bg-r10-paper px-5 py-7 font-r10-body text-r10-grey-700 md:px-8 md:py-9"
    >
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
            <ShowFormFields
                v-model:team-id="form.team_id"
                v-model:name="form.name"
                v-model:description="form.description"
                :teams="teams"
                :errors="form.errors"
            />

            <div class="flex items-center gap-3">
                <R10Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="show-save-button"
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
                        Etendused
                    </h2>
                    <p class="mt-1 text-[15px] text-r10-grey-500">
                        Selle lavastuse kuupäevad. Etendus on see, mille külge
                        tehnikaplaan käib.
                    </p>
                </div>

                <R10Button
                    size="sm"
                    data-test="add-performance-button"
                    @click="openAddPerformance"
                >
                    <Plus class="h-4 w-4" />
                    Lisa etendus
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
                            <th class="px-5 py-3.5">Kuupäev</th>
                            <th class="px-5 py-3.5">Kestus</th>
                            <th class="px-5 py-3.5">Tehnikaplaane</th>
                            <th class="px-5 py-3.5 text-right">
                                <span class="sr-only">Tegevused</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="performance in performances ?? []"
                            :key="performance.id"
                            data-test="performance-row"
                            class="border-b border-r10-grey-200 transition-colors last:border-0 hover:bg-r10-grey-100"
                        >
                            <td
                                class="px-5 py-4 font-medium whitespace-nowrap text-r10-ink"
                            >
                                {{ formatEstonianDate(performance.date) }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                {{
                                    performance.duration
                                        ? `${performance.duration} min`
                                        : '—'
                                }}
                            </td>
                            <td class="px-5 py-4 tabular-nums">
                                {{ performance.technicalPlanCount ?? 0 }}
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div
                                    class="inline-flex items-center justify-end gap-2"
                                >
                                    <button
                                        type="button"
                                        title="Muuda etendust"
                                        data-test="edit-performance-button"
                                        class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-navy bg-white p-2 text-r10-navy transition hover:bg-r10-navy hover:text-white"
                                        @click="
                                            openEditPerformance(performance)
                                        "
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                        <span class="sr-only">Muuda</span>
                                    </button>

                                    <button
                                        type="button"
                                        title="Kustuta etendus"
                                        data-test="delete-performance-button"
                                        class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                                        @click="
                                            openDeletePerformance(performance)
                                        "
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        <span class="sr-only">Kustuta</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-for="row in performances === null &&
                            !performancesFailed
                                ? 2
                                : 0"
                            :key="`performance-skeleton-${row}`"
                            data-test="performance-skeleton-row"
                            class="border-b border-r10-grey-200 last:border-0"
                        >
                            <td v-for="cell in 4" :key="cell" class="px-5 py-4">
                                <span
                                    class="block h-4 animate-pulse rounded-full bg-r10-grey-200"
                                    :class="cell === 1 ? 'w-24' : 'w-12'"
                                />
                            </td>
                        </tr>

                        <tr v-if="performancesFailed">
                            <td
                                colspan="4"
                                class="px-5 py-10 text-center text-[15px] text-r10-orange-700"
                            >
                                Etenduste laadimine ebaõnnestus. Proovi lehte
                                värskendada.
                            </td>
                        </tr>

                        <tr v-else-if="performances?.length === 0">
                            <td
                                colspan="4"
                                class="px-5 py-10 text-center text-[15px] text-r10-grey-500"
                            >
                                Sellel lavastusel pole veel ühtegi etendust.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <PerformanceModal
            v-model:open="performanceModalOpen"
            :show-id="showId"
            :performance="chosenPerformance"
            @saved="reloadPerformances"
        />

        <DeletePerformanceModal
            v-model:open="deleteModalOpen"
            :show-id="showId"
            :performance="chosenPerformance"
            @deleted="reloadPerformances"
        />
    </div>
</template>
