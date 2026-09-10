<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ChangePlanPerformanceModal from '@/components/ChangePlanPerformanceModal.vue';
import {
    statusTone,
    techniciansLine,
} from '@/components/technical-plan/presentPlan';
import R10BackLink from '@/components/technical-plan/R10BackLink.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { formatLocalDate } from '@/lib/date';
import { index, show, updateStatus } from '@/routes/technical-plans';
import type {
    AdminPlanRow,
    PerformanceOption,
    StatusOption,
} from '@/types/technicalPlan';

const props = defineProps<{
    plan: AdminPlanRow;
    statuses: StatusOption[];
    /** The nights the plan may be moved to; empty for a reader who may not. */
    performances: PerformanceOption[];
}>();

defineOptions({
    layout: (props: { plan: AdminPlanRow }) => ({
        breadcrumbs: [
            {
                title: 'Tehnilised plaanid',
                href: index(),
            },
            {
                title: props.plan.formatName ?? 'Nimeta plaan',
                href: show(props.plan.token),
            },
        ],
    }),
});

const page = usePage();

/**
 * Whether this reader is one of the crew, who may both move the plan through
 * its statuses and file it under a different night. Everybody else is shown
 * what the plan says and nothing to change it with.
 */
const canEditPlan = computed(
    () => page.props.auth?.can?.editAllTechnicalPlans === true,
);

/**
 * Who is at the desk that night, as the Planka import last read it off the
 * card. Worded by the same rule the plan document uses, so the crew reads here
 * exactly what the performer was shown.
 */
const technicians = computed(() => techniciansLine(props.plan.technicians));

const changingPerformance = ref(false);

const selectedStatus = ref<string | number>(props.plan.status);

watch(
    () => props.plan.status,
    (status) => {
        selectedStatus.value = status;
    },
);

const statusChanged = computed(
    () => String(selectedStatus.value) !== props.plan.status,
);

function confirmStatus(): void {
    router.visit(updateStatus(props.plan.token), {
        data: { status: String(selectedStatus.value) },
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="plan.formatName ?? 'Nimeta plaan'" />

    <R10Page>
        <StepHeader
            eyebrow="Tehnika"
            :title="plan.formatName ?? 'Nimeta plaan'"
            lead="Tehnilise plaani detailid."
        />

        <!-- The plan itself opens in its own tab, so the details page stays
             where the technician left it. -->
        <R10Button
            external
            :href="plan.url"
            target="_blank"
            rel="noopener"
            data-test="technical-plan-open"
            class="mb-8"
        >
            Vaata plaani
            <ExternalLink class="h-4 w-4" />
        </R10Button>

        <dl class="grid max-w-2xl grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">
            <div>
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Etendus
                </dt>
                <dd
                    class="mt-1 flex flex-wrap items-baseline gap-x-3 text-r10-ink"
                >
                    <span data-test="technical-plan-performance">
                        {{ plan.performanceName ?? '—' }}
                    </span>

                    <!-- A plain link rather than a button: changing the night is
                         a correction, not one of the page's own actions. -->
                    <button
                        v-if="canEditPlan"
                        type="button"
                        class="cursor-pointer text-xs text-r10-grey-500 underline transition hover:text-r10-orange-700"
                        data-test="technical-plan-performance-edit"
                        @click="changingPerformance = true"
                    >
                        Muuda
                    </button>
                </dd>
            </div>

            <div>
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Tiim
                </dt>
                <dd class="mt-1 text-r10-ink">{{ plan.teamName ?? '—' }}</dd>
            </div>

            <div>
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Etenduse kuupäev
                </dt>
                <dd class="mt-1 text-r10-ink">
                    {{ formatLocalDate(plan.performanceStartsAt) }}
                </dd>
            </div>

            <div>
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Asukoht
                </dt>
                <dd
                    class="mt-1 text-r10-ink"
                    data-test="technical-plan-location"
                >
                    {{ plan.performanceLocation ?? '—' }}
                </dd>
            </div>

            <div>
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Tehnik
                </dt>
                <dd
                    class="mt-1 text-r10-ink"
                    data-test="technical-plan-technicians"
                >
                    {{ technicians }}
                </dd>
            </div>

            <div>
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Esitaja
                </dt>
                <dd class="mt-1 text-r10-ink">
                    {{ plan.submittedBy ?? '—' }}
                    <span
                        v-if="plan.submittedByEmail"
                        class="block text-[13px] text-r10-grey-500"
                    >
                        {{ plan.submittedByEmail }}
                    </span>
                </dd>
            </div>

            <div>
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Esitatud
                </dt>
                <dd class="mt-1 text-r10-ink">
                    {{ formatLocalDate(plan.submittedAt) }}
                </dd>
            </div>

            <div :class="{ 'sm:col-span-2': canEditPlan }">
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Staatus
                </dt>
                <dd class="mt-1">
                    <div
                        v-if="canEditPlan"
                        class="flex flex-wrap items-center gap-3"
                    >
                        <R10Select
                            v-model="selectedStatus"
                            :options="statuses"
                            data-test="technical-plan-status-select"
                            class="max-w-xs"
                        />
                        <R10Button
                            type="button"
                            :disabled="!statusChanged"
                            data-test="technical-plan-status-confirm"
                            @click="confirmStatus"
                        >
                            Määra staatus
                        </R10Button>
                    </div>
                    <R10Pill v-else :tone="statusTone(plan.status)" size="md">
                        {{ plan.statusLabel }}
                    </R10Pill>
                </dd>
            </div>
        </dl>

        <R10BackLink :href="index()" class="mt-9" />

        <ChangePlanPerformanceModal
            v-if="canEditPlan"
            v-model:open="changingPerformance"
            :plan-token="plan.token"
            :performance-id="plan.performanceId"
            :performances="performances"
        />
    </R10Page>
</template>
