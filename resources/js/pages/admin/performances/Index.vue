<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { FileClock, MapPin, Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import PerformanceModal from '@/components/PerformanceModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { formatLocalDate, formatLocalTime } from '@/lib/date';
import { performanceStatusLabel } from '@/lib/performanceStatus';
import { index } from '@/routes/admin/performances';
import { show as showPerformance } from '@/routes/formats/performances';
import type {
    AdminPerformanceRow,
    FormatOption,
    FormatTeamOption,
} from '@/types';

const props = defineProps<{
    performances: AdminPerformanceRow[];
    /** Offered only to whoever may add a performance to any format — see below. */
    formats: FormatOption[];
    teams: FormatTeamOption[];
}>();

const page = usePage();

const performanceModalOpen = ref(false);

function reloadPerformances(): void {
    router.reload({ only: ['performances'] });
}

// Reading the whole bill and correcting a night on it are separate rights: the
// house's own people follow every performance here, but only the crew are
// offered the way through to the performance that changes one.
const canEditEveryPerformance = computed(
    () => page.props.auth?.can?.manageAllPerformances === true,
);

/**
 * Whether the archived performances are in the table. Off to begin with: an
 * archived night has been played, so it no longer needs anybody's attention.
 */
const showArchived = ref(false);

const archivedCount = computed(
    () =>
        props.performances.filter(
            (performance) => performance.status === 'archived',
        ).length,
);

const rows = computed(() =>
    showArchived.value
        ? props.performances
        : props.performances.filter(
              (performance) => performance.status !== 'archived',
          ),
);

const lead = computed(() =>
    canEditEveryPerformance.value
        ? 'Kõik maja etendused, olenemata formaadist ja tiimist. Arhiveeritud etendused on vaikimisi peidus. Muutmiseks ava etendus.'
        : 'Kõik maja etendused, olenemata formaadist ja tiimist. Arhiveeritud etendused on vaikimisi peidus. Muuta saab neid oma tiimi formaadi alt.',
);

// Saying nothing has been entered while the checkbox beside it counts out the
// archived nights would read as a fault, so the hidden ones answer for themselves.
const emptyText = computed(() =>
    props.performances.length > 0
        ? 'Kõik etendused on arhiveeritud ja peidetud.'
        : 'Ühtegi etendust pole veel sisestatud.',
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Etendused',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Etendused" />

    <R10Page>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <StepHeader eyebrow="Haldus" title="Etendused" :lead="lead" />

            <R10Button
                v-if="canEditEveryPerformance"
                data-test="add-performance-button"
                :disabled="formats.length === 0"
                @click="performanceModalOpen = true"
            >
                <Plus class="h-4 w-4" />
                Uus etendus
            </R10Button>
        </div>

        <div v-if="archivedCount > 0" class="mb-4 flex justify-end">
            <label class="flex items-center gap-2 text-sm text-r10-grey-700">
                <Checkbox
                    v-model="showArchived"
                    data-test="toggle-archived-performances"
                />
                <span>Näita arhiveerituid ({{ archivedCount }})</span>
            </label>
        </div>

        <R10Table
            :columns="[
                { label: 'Algus' },
                { label: 'Formaat' },
                { label: 'Etteaste' },
                { label: 'Tehnik' },
                { label: 'Olek' },
                { label: 'Tehnikaplaan' },
                { label: 'Tegevused', align: 'right', srOnly: true },
            ]"
            :rows="rows"
            row-test-id="admin-performance-row"
            :empty-text="emptyText"
            error-text="Etenduste laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: performance }">
                <td
                    class="px-5 py-4 align-top font-medium whitespace-nowrap text-r10-ink"
                >
                    {{ formatLocalDate(performance.startsAt) }}
                    <span class="text-r10-grey-500">
                        {{ formatLocalTime(performance.startsAt) }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top">
                    <span
                        class="font-r10-display text-base font-semibold text-r10-ink"
                    >
                        {{ performance.formatName }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top">
                    <span
                        v-if="performance.title"
                        class="block text-r10-ink"
                        data-test="admin-performance-title"
                    >
                        {{ performance.title }}
                    </span>
                    <span
                        class="block text-r10-grey-500"
                        data-test="admin-performance-team"
                    >
                        {{ performance.teamName ?? '—' }}
                    </span>
                    <span
                        v-if="performance.location"
                        class="mt-0.5 flex items-center gap-1 text-xs text-r10-grey-500"
                        data-test="admin-performance-location"
                    >
                        <MapPin class="h-3 w-3 shrink-0" />
                        {{ performance.location }}
                    </span>
                </td>
                <td
                    class="px-5 py-4 align-top"
                    data-test="admin-performance-technicians"
                >
                    {{
                        performance.technicians.length > 0
                            ? performance.technicians.join(', ')
                            : '—'
                    }}
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap">
                    <R10Pill
                        v-if="performance.status === 'draft'"
                        tone="accent"
                        data-test="admin-performance-draft-badge"
                        title="Ülevaatamata etendust ei pakuta tehnikaplaani koostajale."
                        class="border-transparent"
                    >
                        <FileClock class="h-3.5 w-3.5" />
                        {{ performanceStatusLabel(performance.status) }}
                    </R10Pill>
                    <span
                        v-else
                        class="font-r10-body text-[11px] font-bold tracking-[0.08em] text-r10-grey-500 uppercase"
                        data-test="admin-performance-status-label"
                    >
                        {{ performanceStatusLabel(performance.status) }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap">
                    <R10Pill
                        :tone="
                            performance.hasSentTechnicalPlan
                                ? 'success'
                                : 'danger'
                        "
                        data-test="admin-performance-plan-badge"
                    >
                        {{
                            performance.hasSentTechnicalPlan
                                ? 'Saadetud'
                                : 'Puudu'
                        }}
                    </R10Pill>
                </td>
                <td class="px-5 py-4 text-right align-top">
                    <!-- Straight to the performance's own page, which is where
                         a night is corrected. Offered only to those the format
                         would let in. -->
                    <R10Button
                        v-if="canEditEveryPerformance"
                        variant="outline"
                        size="sm"
                        :href="
                            showPerformance([
                                performance.formatId,
                                performance.id,
                            ]).url
                        "
                        data-test="admin-performance-edit-link"
                        class="px-4 py-2"
                    >
                        Detailid
                        <Pencil class="h-3.5 w-3.5" />
                    </R10Button>
                </td>
            </template>
        </R10Table>

        <PerformanceModal
            v-if="canEditEveryPerformance"
            v-model:open="performanceModalOpen"
            :format-id="null"
            :performance="null"
            :teams="teams"
            :formats="formats"
            @saved="reloadPerformances"
        />
    </R10Page>
</template>
