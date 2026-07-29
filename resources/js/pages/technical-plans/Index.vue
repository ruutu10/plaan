<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { statusTone } from '@/components/technical-plan/presentPlan';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { formatEstonianDate } from '@/lib/date';
import { index } from '@/routes/technical-plans';
import type { AdminPlanRow } from '@/types/technicalPlan';

defineProps<{ plans: AdminPlanRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tehnilised plaanid',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Tehnilised plaanid" />

    <R10Page>
        <StepHeader
            eyebrow="Tehnika"
            title="Tehnilised plaanid"
            lead="Kõik esitatud tehnilised plaanid, olenemata staatusest."
        />

        <R10Table
            :columns="[
                { label: 'Etendus' },
                { label: 'Tiim' },
                { label: 'Kuupäev' },
                { label: 'Esitaja' },
                { label: 'Staatus' },
                { label: 'Ava plaan', align: 'right', srOnly: true },
            ]"
            :rows="plans"
            row-test-id="technical-plan-row"
            empty-text="Ühtegi tehnilist plaani pole veel esitatud."
            error-text="Plaanide laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: plan }">
                <td class="px-5 py-4 align-top">
                    <span
                        class="font-r10-display text-base font-semibold text-r10-ink"
                    >
                        {{ plan.showName ?? 'Nimeta plaan' }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top text-r10-grey-500">
                    {{ plan.teamName ?? '—' }}
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap">
                    {{ formatEstonianDate(plan.performanceDate) }}
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
                <td class="px-5 py-4 align-top">
                    <R10Pill :tone="statusTone(plan.status)" size="md">
                        {{ plan.statusLabel }}
                    </R10Pill>
                </td>
                <td class="px-5 py-4 text-right align-top">
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
                </td>
            </template>
        </R10Table>
    </R10Page>
</template>
