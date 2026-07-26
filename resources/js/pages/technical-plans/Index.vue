<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { formatEstonianDate } from '@/lib/date';
import { index } from '@/routes/technical-plans';
import type { AdminPlanRow } from '@/types/technicalPlan';

type Props = {
    plans: AdminPlanRow[];
};

defineProps<Props>();

/** The R10 pill each status is shown as, keyed by its backing value. */
const statusPills: Record<string, string> = {
    draft: 'border-r10-grey-200 bg-r10-grey-100 text-r10-grey-700',
    submitted: 'border-r10-orange bg-r10-orange-100 text-r10-orange-700',
    received: 'border-r10-navy-200 bg-r10-navy-100 text-r10-navy-700',
    archived: 'border-r10-grey-200 bg-white text-r10-grey-500',
};

function statusPill(status: string): string {
    return statusPills[status] ?? statusPills.draft;
}

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

    <div
        class="flex h-full flex-1 flex-col bg-r10-paper px-5 py-7 font-r10-body text-r10-grey-700 md:px-8 md:py-9"
    >
        <StepHeader
            eyebrow="Tehnika"
            title="Tehnilised plaanid"
            lead="Kõik esitatud tehnilised plaanid, olenemata staatusest."
        />

        <div
            class="overflow-x-auto rounded-xl border-2 border-r10-grey-200 bg-white"
        >
            <table class="w-full border-collapse text-left text-sm">
                <thead class="border-b-2 border-r10-navy">
                    <tr
                        class="font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-navy uppercase"
                    >
                        <th class="px-5 py-3.5">Etendus</th>
                        <th class="px-5 py-3.5">Trupp</th>
                        <th class="px-5 py-3.5">Kuupäev</th>
                        <th class="px-5 py-3.5">Esitaja</th>
                        <th class="px-5 py-3.5">Staatus</th>
                        <th class="px-5 py-3.5 text-right">
                            <span class="sr-only">Ava plaan</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="plan in plans"
                        :key="plan.token"
                        data-test="technical-plan-row"
                        class="border-b border-r10-grey-200 transition-colors last:border-0 hover:bg-r10-grey-100"
                    >
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
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full border-2 px-3 py-1 font-r10-body text-[11px] font-bold tracking-[0.08em] whitespace-nowrap uppercase',
                                    statusPill(plan.status),
                                ]"
                            >
                                {{ plan.statusLabel }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right align-top">
                            <a
                                :href="plan.url"
                                target="_blank"
                                rel="noopener"
                                data-test="technical-plan-link"
                                class="inline-flex items-center gap-2 rounded-full border-2 border-r10-navy bg-white px-4 py-2 font-r10-body text-xs font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:bg-r10-navy hover:text-white"
                            >
                                Ava
                                <ExternalLink class="h-3.5 w-3.5" />
                            </a>
                        </td>
                    </tr>

                    <tr v-if="plans.length === 0">
                        <td
                            colspan="6"
                            class="px-5 py-12 text-center text-[15px] text-r10-grey-500"
                        >
                            Ühtegi tehnilist plaani pole veel esitatud.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
