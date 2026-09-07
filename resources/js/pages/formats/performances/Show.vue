<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Head, router, setLayoutProps, useHttp } from '@inertiajs/vue3';
import {
    FileClock,
    Mail,
    Pencil,
    RefreshCw,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ClaudeReasoningLogModal from '@/components/ClaudeReasoningLogModal.vue';
import DeletePerformanceModal from '@/components/DeletePerformanceModal.vue';
import PerformanceModal from '@/components/PerformanceModal.vue';
import PerformanceStaffList from '@/components/PerformanceStaffList.vue';
import RecordOriginFields from '@/components/RecordOriginFields.vue';
import SendPlanReminderModal from '@/components/SendPlanReminderModal.vue';
import R10BackLink from '@/components/technical-plan/R10BackLink.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { useResource } from '@/composables/useResource';
import { formatLocalDate, formatLocalTime } from '@/lib/date';
import {
    claudeLogs as reasoningLogsApi,
    plankaImport as plankaImportApi,
    show as performanceApi,
} from '@/routes/api/formats/performances';
import { edit, index } from '@/routes/formats';
import { show as performancePage } from '@/routes/formats/performances';
import type {
    BreadcrumbItem,
    FormatTeamOption,
    Performance,
    PerformanceReminderRecipient,
} from '@/types';

const props = defineProps<{ formatId: number; performanceId: number }>();

/** The groups this performance may be handed to; the edit form needs it. */
const teams = ref<FormatTeamOption[]>([]);

/**
 * The members of the group playing this performance — who a reminder about the
 * missing technical plan may be sent to. Empty when no group owns the night,
 * which is what leaves the button switched off.
 */
const reminderRecipients = ref<PerformanceReminderRecipient[]>([]);

const editModalOpen = ref(false);
const deleteModalOpen = ref(false);
const reminderModalOpen = ref(false);
/** Where the log dialog reads from; null until the performance has loaded. */
const chosenLogSource = ref<UrlMethodPair | null>(null);
const logOpen = ref(false);

const loader = useHttp();

const {
    data: performance,
    loadFailed,
    reload,
} = useResource(async () => {
    const response = (await loader.submit(
        performanceApi([props.formatId, props.performanceId]),
    )) as {
        data: Performance;
        teams: FormatTeamOption[];
        reminderRecipients: PerformanceReminderRecipient[];
    };

    teams.value = response.teams;
    reminderRecipients.value = response.reminderRecipients;

    nameTheTrail(response.data);
    chosenLogSource.value = reasoningLogsApi([
        props.formatId,
        props.performanceId,
    ]);

    return response.data;
});

/**
 * The page is a shell that fetches its own subject, so the trail starts one
 * crumb short of the format and two short of the performance — completed
 * here once both names are known, the same way the format's own edit page
 * completes its own single trailing crumb.
 */
function nameTheTrail(performance: Performance): void {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Formaadid', href: index() },
            { title: performance.formatName, href: edit(props.formatId) },
            {
                title:
                    performance.title ?? formatLocalDate(performance.startsAt),
                href: performancePage([props.formatId, props.performanceId]),
            },
        ],
    });
}

function openReasoningLog(): void {
    logOpen.value = true;
}

/** The re-read has its own request, so it never fights the page's own loader. */
const reimport = useHttp();

/**
 * Ask for this performance's cards to be read off the Planka board again.
 *
 * Nothing on the page changes when the answer comes back: the run happens on a
 * queue, minutes after the request, so the page says the reading was ordered
 * rather than pretending to show its result. Whoever wants that reloads once
 * the board has been read.
 */
async function refreshFromPlanka(): Promise<void> {
    try {
        await reimport.submit(
            plankaImportApi([props.formatId, props.performanceId]),
        );

        toast.success(
            'Planka import käivitatud. Muudatused ilmuvad mõne minuti pärast.',
        );
    } catch {
        toast.error('Planka impordi käivitamine ebaõnnestus. Proovi uuesti.');
    }
}
</script>

<template>
    <Head :title="performance?.title ?? performance?.formatName ?? 'Etendus'" />

    <R10Page>
        <StepHeader
            eyebrow="Haldus"
            :title="performance?.title ?? performance?.formatName ?? 'Etendus'"
            :lead="
                performance
                    ? `${performance.formatName} — ${formatLocalDate(performance.startsAt)} kell ${formatLocalTime(performance.startsAt)}`
                    : undefined
            "
        />

        <p
            v-if="loadFailed"
            class="max-w-2xl rounded-xl border-2 border-r10-grey-200 bg-white p-5 text-[15px] text-r10-orange-700 md:p-7"
        >
            Etenduse laadimine ebaõnnestus. Proovi lehte värskendada.
        </p>

        <!-- Nothing has arrived yet: stand-ins the shape of the details block. -->
        <div
            v-else-if="performance === null"
            data-test="performance-details-skeleton"
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
        >
            <div v-for="field in 3" :key="field" class="flex flex-col gap-2">
                <span
                    class="block h-3 w-24 animate-pulse rounded-full bg-r10-grey-200"
                />
                <span
                    class="block h-12 animate-pulse rounded-lg bg-r10-grey-100"
                />
            </div>
        </div>

        <div
            v-else
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
        >
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-bold text-r10-grey-500 uppercase">
                        Kuupäev
                    </dt>
                    <dd class="text-r10-ink">
                        {{ formatLocalDate(performance.startsAt) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-r10-grey-500 uppercase">
                        Algusaeg
                    </dt>
                    <dd class="text-r10-ink">
                        {{ formatLocalTime(performance.startsAt) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-r10-grey-500 uppercase">
                        Kestus
                    </dt>
                    <dd class="text-r10-ink">
                        {{
                            performance.duration
                                ? `${performance.duration} min`
                                : '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-xs font-bold text-r10-grey-500 uppercase"
                        title="Loetud Planka kaardilt — seda ei saa siin muuta, ainult uus import uuendab seda."
                    >
                        Asukoht
                    </dt>
                    <dd class="text-r10-ink" data-test="performance-location">
                        {{ performance.location ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-r10-grey-500 uppercase">
                        Esineja tiim
                    </dt>
                    <dd class="text-r10-ink">
                        {{ performance.teamName ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-r10-grey-500 uppercase">
                        Olek
                    </dt>
                    <dd>
                        <R10Pill
                            v-if="performance.isDraft"
                            tone="accent"
                            data-test="performance-draft-badge"
                            class="border-transparent"
                        >
                            <FileClock class="h-3.5 w-3.5" />
                            Ülevaatamata
                        </R10Pill>
                        <span
                            v-else
                            class="font-r10-body text-[11px] font-bold tracking-[0.08em] text-r10-grey-500 uppercase"
                        >
                            Kinnitatud
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-r10-grey-500 uppercase">
                        Tehnikaplaane
                    </dt>
                    <dd class="text-r10-ink">
                        {{ performance.technicalPlanCount ?? 0 }}
                    </dd>
                </div>
            </dl>

            <RecordOriginFields
                :created-by="performance.createdBy"
                :created-at="performance.createdAt"
            />

            <PerformanceStaffList :staff="performance.staff ?? []" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <R10BackLink :href="edit(formatId)" label="Formaadi juurde" />

                <div class="flex items-center gap-2">
                    <R10Button
                        v-if="performance.reasoningLogCount > 0"
                        variant="outline"
                        size="sm"
                        data-test="performance-reasoning-log-button"
                        @click="openReasoningLog"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        Põhjendused
                    </R10Button>

                    <!--
                        Offered only when there is somebody to write to: a
                        performance whose group has no members — or which no
                        group plays — has no reminder to send, the same way the
                        reasoning log is offered only where there is one.
                    -->
                    <R10Button
                        v-if="reminderRecipients.length > 0"
                        variant="outline"
                        size="sm"
                        data-test="send-reminder-button"
                        @click="reminderModalOpen = true"
                    >
                        <Mail class="h-3.5 w-3.5" />
                        Saada meeldetuletus
                    </R10Button>

                    <!--
                        Offered to the crew alone, and only where a board is
                        configured — the server says which, so the button is
                        never shown where the API would refuse it.
                    -->
                    <R10Button
                        v-if="performance.canReimportFromPlanka"
                        variant="outline"
                        size="sm"
                        :disabled="reimport.processing"
                        data-test="refresh-from-planka-button"
                        @click="refreshFromPlanka"
                    >
                        <RefreshCw
                            class="h-3.5 w-3.5"
                            :class="{ 'animate-spin': reimport.processing }"
                        />
                        Värskenda Plankast
                    </R10Button>

                    <R10Button
                        variant="outline"
                        size="sm"
                        data-test="edit-performance-button"
                        @click="editModalOpen = true"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        Muuda
                    </R10Button>

                    <R10Button
                        variant="outline"
                        size="sm"
                        data-test="delete-performance-button"
                        @click="deleteModalOpen = true"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        Kustuta
                    </R10Button>
                </div>
            </div>
        </div>

        <PerformanceModal
            v-model:open="editModalOpen"
            :format-id="formatId"
            :performance="performance"
            :teams="teams"
            @saved="reload"
        />

        <DeletePerformanceModal
            v-model:open="deleteModalOpen"
            :format-id="formatId"
            :performance="performance"
            @deleted="router.visit(edit(formatId).url)"
        />

        <SendPlanReminderModal
            v-model:open="reminderModalOpen"
            :format-id="formatId"
            :performance-id="performanceId"
            :recipients="reminderRecipients"
        />

        <ClaudeReasoningLogModal
            v-model:open="logOpen"
            :source="chosenLogSource"
        />
    </R10Page>
</template>
