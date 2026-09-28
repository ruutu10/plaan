<script setup lang="ts">
/**
 * The listing of technical plans: what is staged, by whom and when, how far
 * the plan has got, and the way into each. Shared by the plans overview and a
 * performance's own page, so a plan reads the same wherever it is listed.
 *
 * `rows` is null until the first response lands — see {@link R10Table}.
 */
import { ExternalLink, Info } from '@lucide/vue';
import { statusTone } from '@/components/technical-plan/presentPlan';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import { formatLocalDate, formatLocalTime } from '@/lib/date';
import { show } from '@/routes/technical-plans';
import type { AdminPlanRow } from '@/types/technicalPlan';

defineProps<{ rows: AdminPlanRow[] | null; emptyText: string }>();
</script>

<template>
    <R10Table
        :columns="[
            { label: 'Etendus' },
            { label: 'Tiim' },
            { label: 'Kuupäev' },
            { label: 'Esitaja' },
            { label: 'Tehnik' },
            { label: 'Staatus' },
            { label: 'Tegevused', align: 'right', srOnly: true },
        ]"
        :rows="rows"
        row-test-id="technical-plan-row"
        :empty-text="emptyText"
        error-text="Plaanide laadimine ebaõnnestus. Proovi lehte värskendada."
    >
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
            </td>
            <td class="px-5 py-4 text-right align-top">
                <div class="flex justify-end gap-2">
                    <R10Button
                        variant="outline"
                        size="sm"
                        :href="show(plan.token)"
                        data-test="technical-plan-details"
                        class="px-4 py-2"
                    >
                        Detailid
                        <Info class="h-3.5 w-3.5" />
                    </R10Button>
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
