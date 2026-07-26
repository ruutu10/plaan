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
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

/** The badge each plan status is shown as, keyed by its backing value. */
const statusVariants: Record<string, 'default' | 'secondary' | 'outline'> = {
    submitted: 'default',
    received: 'secondary',
};

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    return statusVariants[status] ?? 'outline';
}

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam
                    ? dashboard(layoutProps.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card data-test="widget-upcoming-performances">
                <CardHeader>
                    <CardDescription
                        class="flex items-center gap-2 text-xs font-medium tracking-wide uppercase"
                    >
                        <CalendarDays class="h-4 w-4" />
                        Tulevased etendused
                    </CardDescription>
                    <CardTitle class="text-4xl tabular-nums">
                        {{ upcoming.performances }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Etendused, mis on veel ees.
                </CardContent>
            </Card>

            <Card data-test="widget-next-performance">
                <CardHeader>
                    <CardDescription
                        class="flex items-center gap-2 text-xs font-medium tracking-wide uppercase"
                    >
                        <CalendarClock class="h-4 w-4" />
                        Järgmine etendus
                    </CardDescription>
                    <CardTitle class="text-4xl tabular-nums">
                        {{ formatEstonianDate(upcoming.next?.date) }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    <template v-if="upcoming.next">
                        <span class="block font-medium text-foreground">
                            {{ upcoming.next.showName }}
                        </span>
                        <span v-if="upcoming.next.teamName" class="block">
                            {{ upcoming.next.teamName }}
                        </span>
                    </template>
                    <template v-else> Ühtegi etendust pole plaanis. </template>
                </CardContent>
            </Card>

            <Card data-test="widget-missing-plans">
                <CardHeader>
                    <CardDescription
                        class="flex items-center gap-2 text-xs font-medium tracking-wide uppercase"
                    >
                        <FileWarning class="h-4 w-4" />
                        Puuduvad tehnikaplaanid
                    </CardDescription>
                    <CardTitle
                        :class="[
                            'text-4xl tabular-nums',
                            upcoming.missingPlans > 0 ? 'text-destructive' : '',
                        ]"
                    >
                        {{ upcoming.missingPlans }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Tulevased etendused, millele pole plaani esitatud.
                </CardContent>
            </Card>
        </div>

        <Card v-if="canViewAllPlans" data-test="widget-latest-plans">
            <CardHeader>
                <CardTitle>Viimati esitatud tehnikaplaanid</CardTitle>
                <CardDescription>
                    Uuemad ees — ava plaan uues aknas.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ol
                    v-if="latestPlans.length > 0"
                    class="relative space-y-6 border-l border-border pl-6"
                >
                    <li
                        v-for="plan in latestPlans"
                        :key="plan.token"
                        data-test="latest-plan"
                        class="relative"
                    >
                        <span
                            class="absolute top-1.5 -left-[1.8125rem] h-2.5 w-2.5 rounded-full bg-primary ring-4 ring-background"
                        />
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <div>
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ formatEstonianDate(plan.submittedAt) }}
                                </span>
                                <span class="block font-medium">
                                    {{ plan.showName ?? 'Nimeta plaan' }}
                                </span>
                                <span class="text-sm text-muted-foreground">
                                    {{ plan.teamName ?? '—' }}
                                    <template v-if="plan.submittedBy">
                                        · {{ plan.submittedBy }}
                                    </template>
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge :variant="statusVariant(plan.status)">
                                    {{ plan.statusLabel }}
                                </Badge>
                                <a
                                    :href="plan.url"
                                    target="_blank"
                                    rel="noopener"
                                    data-test="latest-plan-link"
                                    class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition hover:bg-accent hover:text-accent-foreground"
                                >
                                    Ava
                                    <ExternalLink class="h-3.5 w-3.5" />
                                </a>
                            </div>
                        </div>
                    </li>
                </ol>

                <p v-else class="text-sm text-muted-foreground">
                    Ühtegi tehnilist plaani pole veel esitatud.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
