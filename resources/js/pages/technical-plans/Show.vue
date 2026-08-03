<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { statusTone } from '@/components/technical-plan/presentPlan';
import R10BackLink from '@/components/technical-plan/R10BackLink.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { formatEstonianDate } from '@/lib/date';
import { index, show, updateStatus } from '@/routes/technical-plans';
import type { AdminPlanRow, StatusOption } from '@/types/technicalPlan';

const props = defineProps<{ plan: AdminPlanRow; statuses: StatusOption[] }>();

defineOptions({
    layout: (props: { plan: AdminPlanRow }) => ({
        breadcrumbs: [
            {
                title: 'Tehnilised plaanid',
                href: index(),
            },
            {
                title: props.plan.showName ?? 'Nimeta plaan',
                href: show(props.plan.token),
            },
        ],
    }),
});

const page = usePage();
const canEditStatus = computed(
    () => page.props.auth?.can?.editAllTechnicalPlans === true,
);

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
    <Head :title="plan.showName ?? 'Nimeta plaan'" />

    <R10Page>
        <StepHeader
            eyebrow="Tehnika"
            :title="plan.showName ?? 'Nimeta plaan'"
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
                    {{ formatEstonianDate(plan.performanceDate) }}
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
                    {{ formatEstonianDate(plan.submittedAt) }}
                </dd>
            </div>

            <div :class="{ 'sm:col-span-2': canEditStatus }">
                <dt
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    Staatus
                </dt>
                <dd class="mt-1">
                    <div
                        v-if="canEditStatus"
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
    </R10Page>
</template>
