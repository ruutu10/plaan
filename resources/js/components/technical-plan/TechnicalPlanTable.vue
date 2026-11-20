<script setup lang="ts">
/**
 * The listing of technical plans: what is staged, by whom and when, how far
 * the plan has got, and the way into each. Shared by the plans overview and a
 * performance's own page, so a plan reads the same wherever it is listed.
 *
 * `rows` is null until the first response lands — see {@link R10Table}.
 */
import { ExternalLink } from '@lucide/vue';
import { statusTone } from '@/components/technical-plan/presentPlan';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import type { ListingColumn } from '@/composables/useListingTable';
import { instantSortValue } from '@/composables/useListingTable';
import { formatLocalDate, formatLocalTime } from '@/lib/date';
import { show } from '@/routes/technical-plans';
import type { AdminPlanRow } from '@/types/technicalPlan';

withDefaults(
    defineProps<{
        rows: AdminPlanRow[] | null;
        emptyText: string;
        /**
         * Whether the table offers its search box and filters. The overview
         * does; a single performance's handful of plans needs only sorting.
         */
        narrowable?: boolean;
    }>(),
    { narrowable: false },
);

const columns: ListingColumn<AdminPlanRow>[] = [
    {
        id: 'format',
        label: 'Etendus',
        sortBy: (plan) => plan.formatName,
        searchBy: (plan) => [plan.formatName, plan.performanceTitle],
        filterBy: (plan) => plan.formatName,
    },
    {
        id: 'team',
        label: 'Tiim',
        sortBy: (plan) => plan.teamName,
        searchBy: (plan) => plan.teamName,
        filterBy: (plan) => plan.teamName,
    },
    {
        id: 'startsAt',
        label: 'Kuupäev',
        sortBy: (plan) => instantSortValue(plan.performanceStartsAt),
    },
    {
        id: 'submittedBy',
        label: 'Esitaja',
        sortBy: (plan) => plan.submittedBy,
        searchBy: (plan) => [plan.submittedBy, plan.submittedByEmail],
    },
    {
        id: 'technicians',
        label: 'Tehnik',
        searchBy: (plan) => plan.technicians,
        filterBy: (plan) => plan.technicians,
    },
    {
        id: 'status',
        label: 'Staatus',
        sortBy: (plan) => plan.statusLabel,
        filterBy: (plan) => plan.statusLabel,
    },
    { label: 'Tegevused', align: 'right', srOnly: true },
];
</script>

<template>
    <R10Table
        :columns="columns"
        :rows="rows"
        :narrowable="narrowable"
        :row-href="(plan) => show(plan.token).url"
        row-test-id="technical-plan-row"
        :empty-text="emptyText"
        search-placeholder="Otsi etendust, tiimi, esitajat või tehnikut…"
        no-matches-text="Otsingule vastavaid plaane pole."
        error-text="Plaanide laadimine ebaõnnestus. Proovi lehte värskendada."
    >
        <template v-if="$slots.toolbar" #toolbar>
            <slot name="toolbar" />
        </template>

        <template #row="{ row: plan }">
            <td class="px-5 py-4 align-top">
                <span
                    class="font-r10-display text-base font-semibold text-r10-ink"
                >
                    {{ plan.formatName ?? 'Nimeta plaan' }}
                </span>
            </td>
            <td class="px-5 py-4 align-top text-r10-grey-500">
                {{ plan.teamName ?? '—' }}
            </td>
            <td class="px-5 py-4 align-top whitespace-nowrap">
                {{ formatLocalDate(plan.performanceStartsAt) }}
                {{ formatLocalTime(plan.performanceStartsAt) }}
            </td>
            <td class="px-5 py-4 align-top">
                <span class="block text-r10-ink">
                    {{ plan.submittedBy ?? '—' }}
                </span>
                <span
                    v-if="plan.submittedByEmail"
                    class="mt-0.5 block text-[13px] text-r10-grey-500"
                >
                    {{ plan.submittedByEmail }}
                </span>
            </td>
            <td
                class="px-5 py-4 align-top"
                data-test="technical-plan-technicians"
            >
                {{
                    plan.technicians.length > 0
                        ? plan.technicians.join(', ')
                        : '—'
                }}
            </td>
            <td class="px-5 py-4 align-top">
                <R10Pill :tone="statusTone(plan.status)" size="md">
                    {{ plan.statusLabel }}
                </R10Pill>
                <span
                    class="mt-1 block text-[13px] whitespace-nowrap text-r10-grey-500"
                    data-test="technical-plan-approved-at"
                >
                    {{
                        plan.approvedAt
                            ? `Kinnitatud ${formatLocalDate(plan.approvedAt)}`
                            : 'Pole veel kinnitatud'
                    }}
                </span>
            </td>
            <td class="px-5 py-4 text-right align-top">
                <div class="flex justify-end gap-2">
                    <R10Button
                        variant="outline"
                        size="sm"
                        external
                        :href="plan.url"
                        target="_blank"
                        rel="noopener"
                        data-test="technical-plan-link"
                        class="px-4 py-2"
                    >
                        Ava
                        <ExternalLink class="h-3.5 w-3.5" />
                    </R10Button>
                </div>
            </td>
        </template>
    </R10Table>
</template>
