<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { isArchived, isDraft } from '@/components/technical-plan/plan';
import R10Page from '@/components/technical-plan/R10Page.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import TechnicalPlanTable from '@/components/technical-plan/TechnicalPlanTable.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { index } from '@/routes/technical-plans';
import type { AdminPlanRow } from '@/types/technicalPlan';

const props = defineProps<{ plans: AdminPlanRow[] }>();

const page = usePage();

/**
 * Whether the half-written plans are in the table. Off to begin with: a draft
 * is its author's own work in progress and nobody has been asked to read it
 * yet, so it does not stand between the crew and the plans that were handed in.
 */
const showDrafts = ref(false);

/**
 * Whether the archived plans are in the table. Off to begin with: an archived
 * plan's night has been played, so the crew no longer works from it.
 */
const showArchived = ref(false);

const draftCount = computed(
    () => props.plans.filter((plan) => isDraft(plan.status)).length,
);

const archivedCount = computed(
    () => props.plans.filter((plan) => isArchived(plan.status)).length,
);

const rows = computed(() =>
    props.plans.filter(
        (plan) =>
            (showDrafts.value || !isDraft(plan.status)) &&
            (showArchived.value || !isArchived(plan.status)),
    ),
);

// The listing reaches as far as the reader does, so say which listing this is
// rather than promising the whole house to somebody shown one corner of it.
const lead = computed(() =>
    page.props.auth?.can?.viewAllTechnicalPlans
        ? 'Kõik tehnikatiimile esitatud plaanid. Mustandid ja arhiveeritud plaanid on vaikimisi peidus.'
        : 'Sinu ja sinu tiimide tehnikatiimile esitatud plaanid. Mustandid ja arhiveeritud plaanid on vaikimisi peidus.',
);

const emptyText = computed(() => {
    // Saying there is nothing here while the filters beside it count out the
    // hidden plans would read as a fault, so the hidden ones answer for themselves.
    if (props.plans.length > 0) {
        return 'Kõik plaanid on filtritega peidetud.';
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

        <div
            v-if="draftCount > 0 || archivedCount > 0"
            class="mb-4 flex flex-wrap justify-end gap-x-6 gap-y-2"
        >
            <label
                v-if="draftCount > 0"
                class="flex items-center gap-2 text-sm text-r10-grey-700"
            >
                <Checkbox v-model="showDrafts" data-test="toggle-drafts" />
                <span>Näita mustandeid ({{ draftCount }})</span>
            </label>
            <label
                v-if="archivedCount > 0"
                class="flex items-center gap-2 text-sm text-r10-grey-700"
            >
                <Checkbox v-model="showArchived" data-test="toggle-archived" />
                <span>Näita arhiveerituid ({{ archivedCount }})</span>
            </label>
        </div>

        <TechnicalPlanTable :rows="rows" :empty-text="emptyText" />
    </R10Page>
</template>
