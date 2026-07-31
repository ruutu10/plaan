<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarDays,
    ExternalLink,
    FileWarning,
} from '@lucide/vue';
import { computed } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { statusTone } from '@/components/technical-plan/presentPlan';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import { formatEstonianDate } from '@/lib/date';
import { dashboard } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';
import type { UpcomingSummary } from '@/types/dashboard';
import type { AdminPlanRow } from '@/types/technicalPlan';

defineProps<{
    pendingInvitations?: DashboardInvitation[];
    upcoming: UpcomingSummary;
    latestPlans: AdminPlanRow[];
}>();

const page = usePage();

// The timeline names who handed in what and links into the plans themselves;
// the server only fills it for the technical crew.
const canViewAllPlans = computed(
    () => page.props.auth?.can?.viewAllTechnicalPlans === true,
);

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Töölaud',
                href: layoutProps.currentTeam
                    ? dashboard(layoutProps.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Töölaud" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <R10Page>
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                data-test="widget-upcoming-performances"
                class="rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
            >
                <div
                    class="flex items-center gap-2 font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    <CalendarDays class="h-4 w-4" />
                    Tulevased etendused
                </div>
                <div
                    class="mt-2 font-r10-display text-4xl font-bold text-r10-ink tabular-nums"
                >
                    {{ upcoming.performances }}
                </div>
                <p class="mt-3 text-sm text-r10-grey-500">
                    Etendused, mis on veel ees.
                </p>
            </div>

            <div
                data-test="widget-next-performance"
                class="rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
            >
                <div
                    class="flex items-center gap-2 font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    <CalendarClock class="h-4 w-4" />
                    Järgmine etendus
                </div>
                <div
                    class="mt-2 font-r10-display text-4xl font-bold text-r10-ink tabular-nums"
                >
                    {{ formatEstonianDate(upcoming.next?.date) }}
                </div>
                <div class="mt-3 text-sm text-r10-grey-500">
                    <template v-if="upcoming.next">
                        <span class="block font-bold text-r10-ink">
                            {{ upcoming.next.showName }}
                        </span>
                        <span class="block">
                            Algus {{ upcoming.next.startTime }}
                        </span>
                        <span v-if="upcoming.next.teamName" class="block">
                            {{ upcoming.next.teamName }}
                        </span>
                    </template>
                    <template v-else> Ühtegi etendust pole plaanis. </template>
                </div>
            </div>

            <div
                data-test="widget-missing-plans"
                class="rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
            >
                <div
                    class="flex items-center gap-2 font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                >
                    <FileWarning class="h-4 w-4" />
                    Puuduvad tehnikaplaanid
                </div>
                <div
                    :class="[
                        'mt-2 font-r10-display text-4xl font-bold tabular-nums',
                        upcoming.missingPlans > 0
                            ? 'text-r10-error'
                            : 'text-r10-ink',
                    ]"
                >
                    {{ upcoming.missingPlans }}
                </div>
                <p class="mt-3 text-sm text-r10-grey-500">
                    Tulevased etendused, millele pole plaani esitatud.
                </p>
            </div>
        </div>

        <div
            v-if="canViewAllPlans"
            data-test="widget-latest-plans"
            class="mt-4 rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
        >
            <h2
                class="font-r10-display text-lg font-bold tracking-[0.03em] text-r10-navy uppercase"
            >
                Viimati esitatud tehnikaplaanid
            </h2>
            <p class="mt-1 text-sm text-r10-grey-500">
                Uuemad ees — ava plaan uues aknas.
            </p>

            <ol
                v-if="latestPlans.length > 0"
                class="relative mt-6 space-y-6 border-l border-r10-grey-200 pl-6"
            >
                <li
                    v-for="plan in latestPlans"
                    :key="plan.token"
                    data-test="latest-plan"
                    class="relative"
                >
                    <span
                        class="absolute top-1.5 -left-[1.8125rem] h-2.5 w-2.5 rotate-45 rounded-[1px] bg-r10-orange ring-4 ring-white"
                    />
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div>
                            <span
                                class="text-xs text-r10-grey-500 tabular-nums"
                            >
                                {{ formatEstonianDate(plan.submittedAt) }}
                            </span>
                            <span
                                class="block font-r10-display text-base font-semibold text-r10-ink"
                            >
                                {{ plan.showName ?? 'Nimeta plaan' }}
                            </span>
                            <span class="text-sm text-r10-grey-500">
                                {{ plan.teamName ?? '—' }}
                                <template v-if="plan.submittedBy">
                                    · {{ plan.submittedBy }}
                                </template>
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <R10Pill :tone="statusTone(plan.status)" size="md">
                                {{ plan.statusLabel }}
                            </R10Pill>
                            <R10Button
                                variant="outline"
                                size="sm"
                                external
                                :href="plan.url"
                                target="_blank"
                                rel="noopener"
                                data-test="latest-plan-link"
                                class="px-4 py-2"
                            >
                                Ava
                                <ExternalLink class="h-3.5 w-3.5" />
                            </R10Button>
                        </div>
                    </div>
                </li>
            </ol>

            <p v-else class="mt-6 text-sm text-r10-grey-500">
                Ühtegi tehnilist plaani pole veel esitatud.
            </p>
        </div>
    </R10Page>
</template>
