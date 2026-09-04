<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ExternalLink, Info } from '@lucide/vue';
import { computed, ref } from 'vue';
import { isDraft } from '@/components/technical-plan/plan';
import { statusTone } from '@/components/technical-plan/presentPlan';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { formatLocalDate } from '@/lib/date';
import { index, show } from '@/routes/technical-plans';
import type { AdminPlanRow } from '@/types/technicalPlan';

const props = defineProps<{ plans: AdminPlanRow[] }>();

const page = usePage();

/**
 * Whether the half-written plans are in the table. Off to begin with: a draft
 * is its author's own work in progress and nobody has been asked to read it
 * yet, so it does not stand between the crew and the plans that were handed in.
 */
const showDrafts = ref(false);

const draftCount = computed(
    () => props.plans.filter((plan) => isDraft(plan.status)).length,
);

const rows = computed(() =>
    showDrafts.value
        ? props.plans
        : props.plans.filter((plan) => !isDraft(plan.status)),
);

// The listing reaches as far as the reader does, so say which listing this is
// rather than promising the whole house to somebody shown one corner of it.
const lead = computed(() =>
    page.props.auth?.can?.viewAllTechnicalPlans
        ? 'Kõik tehnikutiimile esitatud plaanid. Mustandid on vaikimisi peidus.'
        : 'Sinu ja sinu tiimide tehnikutiimile esitatud plaanid. Mustandid on vaikimisi peidus.',
);

const emptyText = computed(() => {
    // Saying there is nothing here while the button beside it counts out the
    // drafts would read as a fault, so the hidden ones answer for themselves.
    if (draftCount.value > 0) {
        return 'Ühtegi plaani pole veel esitatud — mustandid on peidetud.';
    }

    return page.props.auth?.can?.viewAllTechnicalPlans
        ? 'Ühtegi tehnilist plaani pole veel esitatud.'
        : 'Sinul ja sinu tiimidel pole veel ühtegi tehnilist plaani.';
});

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
        <StepHeader eyebrow="Tehnika" title="Tehnilised plaanid" :lead="lead" />

        <div v-if="draftCount > 0" class="mb-4 flex justify-end">
            <R10Button
                variant="outline"
                size="sm"
                data-test="toggle-drafts"
                class="px-4 py-2"
                @click="showDrafts = !showDrafts"
            >
                {{
                    showDrafts
                        ? 'Peida mustandid'
                        : `Näita mustandeid (${draftCount})`
                }}
            </R10Button>
        </div>

        <R10Table
            :columns="[
                { label: 'Etendus' },
                { label: 'Tiim' },
                { label: 'Kuupäev' },
                { label: 'Esitaja' },
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
    </R10Page>
</template>
