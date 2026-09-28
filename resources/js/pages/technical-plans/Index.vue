<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { isDraft } from '@/components/technical-plan/plan';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import TechnicalPlanTable from '@/components/technical-plan/TechnicalPlanTable.vue';
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
        ? 'Kõik tehnikatiimile esitatud plaanid. Mustandid on vaikimisi peidus.'
        : 'Sinu ja sinu tiimide tehnikatiimile esitatud plaanid. Mustandid on vaikimisi peidus.',
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

        <TechnicalPlanTable :rows="rows" :empty-text="emptyText" />
    </R10Page>
</template>
