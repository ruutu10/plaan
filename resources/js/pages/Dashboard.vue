<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarDays,
    Drama,
    ExternalLink,
    FileWarning,
    MapPin,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { statusTone } from '@/components/technical-plan/presentPlan';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10RecordLink from '@/components/technical-plan/R10RecordLink.vue';
import { formatLocalDate, formatLocalTime } from '@/lib/date';
import { dashboard } from '@/routes';
import type { DashboardInvitation } from '@/types';
import type {
    OwnBill,
    TodaysPerformance,
    UpcomingSummary,
} from '@/types/dashboard';
import type { AdminPlanRow } from '@/types/technicalPlan';

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    upcoming: UpcomingSummary;
    today: TodaysPerformance[];
    myPerformances: OwnBill;
    latestPlans: AdminPlanRow[];
}>();

const page = usePage();

// An account nobody has put on a staff list has no strip of the bill to show,
// and an empty timeline reads as something broken rather than as nothing owed.
const hasOwnBill = computed(
    () =>
        props.myPerformances.past.length > 0 ||
        props.myPerformances.upcoming.length > 0,
);

/**
 * The reader's own bill as one run of rows, the line between what is behind
 * them and what is ahead sitting in it as a row of its own. Both sides carry
 * the same markup, so they are walked in one pass rather than written twice —
 * and the line stays in place on a side that happens to be empty, so a newcomer
 * with nothing behind them still reads their nights as being ahead.
 */
const ownBillRows = computed(() => [
    ...props.myPerformances.past.map((performance) => ({
        key: `past-${performance.id}`,
        past: true,
        performance,
    })),
    { key: 'now', past: false, performance: null },
    ...props.myPerformances.upcoming.map((performance) => ({
        key: `upcoming-${performance.id}`,
        past: false,
        performance,
    })),
]);

// The timeline names who handed in what and links into the plans themselves;
// the server only fills it for the technical crew.
const canViewAllPlans = computed(
    () => page.props.auth?.can?.viewAllTechnicalPlans === true,
);

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: 'Töölaud',
                href: dashboard(),
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
                    <!-- The date is the night itself, so it opens the
                    performance; the format's name opens the format. -->
                    <R10RecordLink
                        :href="upcoming.next?.performanceUrl"
                        data-test="next-performance-link"
                    >
                        {{ formatLocalDate(upcoming.next?.startsAt) }}
                    </R10RecordLink>
                </div>
                <div class="mt-3 text-sm text-r10-grey-500">
                    <template v-if="upcoming.next">
                        <R10RecordLink
                            :href="upcoming.next.formatUrl"
                            class="block font-bold text-r10-ink"
                            data-test="next-format-link"
                        >
                            {{ upcoming.next.formatName }}
                        </R10RecordLink>
                        <span class="block">
                            Algus
                            {{ formatLocalTime(upcoming.next.startsAt) }}
                        </span>
                        <span v-if="upcoming.next.teamName" class="block">
                            {{ upcoming.next.teamName }}
                        </span>
                        <span
                            v-if="upcoming.next.location"
                            class="flex items-center gap-1"
                            data-test="next-performance-location"
                        >
                            <MapPin class="h-3.5 w-3.5 shrink-0" />
                            {{ upcoming.next.location }}
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

                <p class="mt-1 text-xs text-r10-grey-500">
                    Tehnikaplaani oodatakse alates
                    {{ upcoming.planExpectedWithinDays }} päevast enne etendust
                    — kaugemaid etendusi siin ei loeta.
                </p>
            </div>
        </div>

        <!-- Tonight's bill and the reader's own place on it sit side by side:
        both are lists of nights rather than counters, and they answer the same
        question from the house's end and the reader's. They stack on a narrow
        screen, where two columns of them would be unreadable. -->
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div
                data-test="widget-todays-plans"
                class="rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
            >
                <h2
                    class="flex items-center gap-2 font-r10-display text-lg font-bold tracking-[0.03em] text-r10-navy uppercase"
                >
                    <Drama class="h-5 w-5" />
                    Tänaste etenduste tehnikaplaanid
                </h2>

                <ul v-if="today.length > 0" class="mt-6 space-y-3">
                    <li
                        v-for="performance in today"
                        :key="performance.id"
                        data-test="todays-performance"
                        class="rounded-[16px] border border-r10-grey-200 p-4"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <div>
                                <R10RecordLink
                                    :href="performance.formatUrl"
                                    class="font-r10-display text-base font-semibold text-r10-ink"
                                    data-test="todays-format-link"
                                >
                                    {{ performance.formatName }}
                                </R10RecordLink>
                                <!-- The act's own name is the performance's, so it
                            opens the performance rather than the format. -->
                                <R10RecordLink
                                    v-if="performance.title"
                                    :href="performance.performanceUrl"
                                    class="ml-2 text-sm text-r10-grey-500"
                                    data-test="todays-performance-title-link"
                                >
                                    {{ performance.title }}
                                </R10RecordLink>
                                <span class="block text-sm text-r10-grey-500">
                                    {{ performance.teamName ?? '—' }}
                                </span>
                                <span
                                    v-if="performance.location"
                                    class="flex items-center gap-1 text-sm text-r10-grey-500"
                                    data-test="todays-performance-location"
                                >
                                    <MapPin class="h-3.5 w-3.5 shrink-0" />
                                    {{ performance.location }}
                                </span>
                            </div>
                            <R10RecordLink
                                :href="performance.performanceUrl"
                                class="font-r10-display text-lg font-bold text-r10-ink tabular-nums"
                                data-test="todays-performance-link"
                            >
                                {{ formatLocalTime(performance.startsAt) }}
                            </R10RecordLink>
                        </div>

                        <ul
                            v-if="performance.plans.length > 0"
                            class="mt-3 space-y-2"
                        >
                            <li
                                v-for="(plan, index) in performance.plans"
                                :key="plan.token ?? `hidden-${index}`"
                                data-test="todays-plan"
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-2">
                                    <R10Pill
                                        :tone="
                                            plan.visible
                                                ? statusTone(plan.status)
                                                : 'muted'
                                        "
                                    >
                                        {{ plan.statusLabel }}
                                    </R10Pill>
                                    <span
                                        v-if="plan.submittedBy"
                                        class="text-sm text-r10-grey-500"
                                    >
                                        {{ plan.submittedBy }}
                                    </span>
                                </div>
                                <R10Button
                                    v-if="plan.visible && plan.url"
                                    variant="outline"
                                    size="sm"
                                    external
                                    :href="plan.url"
                                    target="_blank"
                                    rel="noopener"
                                    data-test="todays-plan-link"
                                    class="px-4 py-2"
                                >
                                    Ava
                                    <ExternalLink class="h-3.5 w-3.5" />
                                </R10Button>
                            </li>
                        </ul>

                        <p
                            v-else
                            data-test="todays-plan-missing"
                            class="mt-3 text-sm font-bold text-r10-error"
                        >
                            Tehnikaplaan puudub
                        </p>
                    </li>
                </ul>

                <p v-else class="mt-6 text-sm text-r10-grey-500">
                    Täna ei ole ühtegi etendust.
                </p>
            </div>

            <div
                data-test="widget-my-performances"
                class="rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
            >
                <h2
                    class="flex items-center gap-2 font-r10-display text-lg font-bold tracking-[0.03em] text-r10-navy uppercase"
                >
                    <UserRound class="h-5 w-5" />
                    Minu järgmised etendused
                </h2>
                <p class="mt-1 text-sm text-r10-grey-500">
                    Õhtud, millel sul on roll — kolm viimast ja kolm järgmist.
                </p>

                <ol
                    v-if="hasOwnBill"
                    class="relative mt-6 space-y-6 border-l border-r10-grey-200 pl-6"
                >
                    <template v-for="row in ownBillRows" :key="row.key">
                        <!-- Where the reader is standing: everything above has been
                    played, everything below is still to come. -->
                        <li
                            v-if="!row.performance"
                            data-test="my-performances-divider"
                            class="-ml-6 flex items-center gap-3"
                        >
                            <span
                                class="h-[3px] flex-1 rounded-full bg-r10-orange"
                            />
                            <span
                                class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-orange uppercase"
                            >
                                Praegu
                            </span>
                            <span
                                class="h-[3px] flex-1 rounded-full bg-r10-orange"
                            />
                        </li>

                        <li
                            v-else
                            data-test="my-performance"
                            :class="['relative', row.past && 'opacity-70']"
                        >
                            <span
                                :class="[
                                    'absolute top-1.5 -left-[1.8125rem] h-2.5 w-2.5 rotate-45 rounded-[1px] ring-4 ring-white',
                                    row.past
                                        ? 'bg-r10-grey-200'
                                        : 'bg-r10-orange',
                                ]"
                            />
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div>
                                    <span
                                        class="text-xs text-r10-grey-500 tabular-nums"
                                    >
                                        {{
                                            formatLocalDate(
                                                row.performance.startsAt,
                                            )
                                        }}
                                        ·
                                        {{
                                            formatLocalTime(
                                                row.performance.startsAt,
                                            )
                                        }}
                                    </span>
                                    <!-- The night is what the reader is on, so the
                                name opens the performance rather than the
                                format behind it. -->
                                    <R10RecordLink
                                        :href="row.performance.performanceUrl"
                                        class="block font-r10-display text-base font-semibold text-r10-ink"
                                        data-test="my-performance-link"
                                    >
                                        {{ row.performance.formatName }}
                                        <template v-if="row.performance.title">
                                            · {{ row.performance.title }}
                                        </template>
                                    </R10RecordLink>
                                    <span class="text-sm text-r10-grey-500">
                                        {{ row.performance.teamName ?? '—' }}
                                    </span>
                                    <span
                                        v-if="row.performance.location"
                                        class="flex items-center gap-1 text-sm text-r10-grey-500"
                                        data-test="my-performance-location"
                                    >
                                        <MapPin class="h-3.5 w-3.5 shrink-0" />
                                        {{ row.performance.location }}
                                    </span>
                                </div>
                                <!-- A person can hold more than one job on a night,
                            so every one of them is named. -->
                                <div class="flex flex-wrap items-center gap-2">
                                    <R10Pill
                                        v-for="role in row.performance.roles"
                                        :key="role.role"
                                        :tone="row.past ? 'muted' : 'accent'"
                                        size="md"
                                        data-test="my-performance-role"
                                    >
                                        {{ role.label }}
                                    </R10Pill>
                                </div>
                            </div>
                        </li>
                    </template>
                </ol>

                <p
                    v-else
                    data-test="my-performances-empty"
                    class="mt-6 text-sm text-r10-grey-500"
                >
                    Sind ei ole ühelegi etendusele kirja pandud.
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
                                {{ formatLocalDate(plan.submittedAt) }}
                            </span>
                            <R10RecordLink
                                :href="plan.formatUrl"
                                class="block font-r10-display text-base font-semibold text-r10-ink"
                                data-test="latest-plan-format-link"
                            >
                                {{ plan.formatName ?? 'Nimeta plaan' }}
                            </R10RecordLink>
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
