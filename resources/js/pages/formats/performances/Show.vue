<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Head, router, setLayoutProps, useHttp } from '@inertiajs/vue3';
import {
    Download,
    ExternalLink,
    FileClock,
    Mail,
    Pencil,
    Plus,
    RefreshCw,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { download as downloadRecording } from '@/actions/App/Http/Controllers/PerformanceRecordingController';
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
import R10SectionHeader from '@/components/technical-plan/R10SectionHeader.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import TechnicalPlanTable from '@/components/technical-plan/TechnicalPlanTable.vue';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useResource } from '@/composables/useResource';
import { formatLocalDate, formatLocalTime } from '@/lib/date';
import {
    acceptsNewPlan,
    isStillToCome,
    performanceStatusLabel,
} from '@/lib/performanceStatus';
import { dashboard } from '@/routes';
import {
    claudeLogs as reasoningLogsApi,
    plankaImport as plankaImportApi,
    show as performanceApi,
} from '@/routes/api/formats/performances';
import { store as plotSummaryApi } from '@/routes/api/formats/performances/plot-summary';
import { edit, index } from '@/routes/formats';
import { show as performancePage } from '@/routes/formats/performances';
import { index as planWizard } from '@/routes/technical-plan';
import type {
    BreadcrumbItem,
    FormatTeamOption,
    Performance,
    PerformanceAbilities,
    PerformanceReminderRecipient,
    PerformanceStaffRoleOption,
} from '@/types';
import type { AdminPlanRow } from '@/types/technicalPlan';

const props = defineProps<{ formatId: number; performanceId: number }>();

/** The groups this performance may be handed to; the edit form needs it. */
const teams = ref<FormatTeamOption[]>([]);

/**
 * The members of the group playing this performance and the night's imported
 * staff — who a reminder about the missing technical plan may be sent to.
 * Empty when there is nobody, which is what leaves the button switched off.
 */
const reminderRecipients = ref<PerformanceReminderRecipient[]>([]);

/**
 * The plans handed in for this performance, drafts left out. Null until the
 * performance has loaded, which is what the table's skeleton keys off.
 */
const technicalPlans = ref<AdminPlanRow[] | null>(null);

/**
 * The jobs somebody may be put on this night's staff in by hand. Empty
 * for a reader who may not change the night, which hides the add button.
 */
const staffRoles = ref<PerformanceStaffRoleOption[]>([]);

/**
 * What this reader may do here. The night's staff open the page to read it;
 * changing it stays with its groups and the crew. Nothing is offered until the
 * server has said.
 */
const can = ref<PerformanceAbilities>({ edit: false, openFormat: false });

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
        technicalPlans: AdminPlanRow[];
        staffRoles: PerformanceStaffRoleOption[];
        can: PerformanceAbilities;
    };

    can.value = response.can;
    teams.value = response.teams;
    reminderRecipients.value = response.reminderRecipients;
    technicalPlans.value = response.technicalPlans;
    staffRoles.value = response.staffRoles;

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
    const self = {
        title: performance.title ?? formatLocalDate(performance.startsAt),
        href: performancePage([props.formatId, props.performanceId]),
    };

    // Somebody who only works this night cannot open its format, so their
    // trail leads home instead of to a page that would turn them away.
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: can.value.openFormat
            ? [
                  { title: 'Formaadid', href: index() },
                  { title: performance.formatName, href: edit(props.formatId) },
                  self,
              ]
            : [{ title: 'Töölaud', href: dashboard() }, self],
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

/** Asking for a fresh plot summary has its own request, like the re-read. */
const summaryRequest = useHttp();

/**
 * Ask for the plot to be read off the recording's subtitles again. Queued like
 * the Planka re-read, so the page says it was ordered rather than waiting.
 */
async function regeneratePlotSummary(): Promise<void> {
    try {
        await summaryRequest.submit(
            plotSummaryApi([props.formatId, props.performanceId]),
        );

        toast.success(
            'Sisukokkuvõte tellitud. Uus kokkuvõte ilmub mõne minuti pärast, kui subtiitrid on olemas.',
        );
    } catch {
        toast.error('Sisukokkuvõtte tellimine ebaõnnestus. Proovi uuesti.');
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
            class="rounded-xl border-2 border-r10-grey-200 bg-white p-5 text-[15px] text-r10-orange-700 md:p-7"
        >
            Etenduse laadimine ebaõnnestus. Proovi lehte värskendada.
        </p>

        <!-- Nothing has arrived yet: stand-ins the shape of the details block. -->
        <div
            v-else-if="performance === null"
            data-test="performance-details-skeleton"
            class="flex flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
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
            class="flex flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
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
                            v-if="performance.status === 'draft'"
                            tone="accent"
                            data-test="performance-draft-badge"
                            class="border-transparent"
                        >
                            <FileClock class="h-3.5 w-3.5" />
                            {{ performanceStatusLabel(performance.status) }}
                        </R10Pill>
                        <span
                            v-else
                            class="font-r10-body text-[11px] font-bold tracking-[0.08em] text-r10-grey-500 uppercase"
                            data-test="performance-status-label"
                        >
                            {{ performanceStatusLabel(performance.status) }}
                        </span>
                    </dd>
                </div>
                <!-- Shown to everybody who may open the night: watching a
                     recording is not the crew's right alone, only saying where
                     one is. -->
                <div>
                    <dt class="text-xs font-bold text-r10-grey-500 uppercase">
                        Salvestus
                    </dt>
                    <dd class="flex flex-col gap-1 text-r10-ink">
                        <a
                            v-if="performance.recording?.itemUrl"
                            :href="performance.recording.itemUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            data-test="performance-recording-link"
                            class="inline-flex w-fit items-center gap-1.5 font-medium text-r10-navy underline underline-offset-2 hover:text-r10-orange-700"
                        >
                            <ExternalLink class="h-3.5 w-3.5" />
                            Vaata Jellyfinis
                        </a>
                        <!-- A plain link, not an Inertia visit: the browser has
                             to take this as a file download. -->
                        <a
                            v-if="performance.recording?.canDownload"
                            :href="downloadRecording.url(props.performanceId)"
                            data-test="performance-recording-download"
                            class="inline-flex w-fit items-center gap-1.5 font-medium text-r10-navy underline underline-offset-2 hover:text-r10-orange-700"
                        >
                            <Download class="h-3.5 w-3.5" />
                            Laadi alla
                        </a>
                        <span
                            v-if="
                                performance.recording?.canDownload &&
                                performance.recording.file
                            "
                            class="text-[13px] break-all text-r10-grey-500"
                            data-test="performance-recording-file"
                        >
                            {{ performance.recording.file.name }} ·
                            {{ performance.recording.file.sizeLabel }}
                            <template v-if="performance.recording.file.addedOn">
                                · lisatud
                                {{ performance.recording.file.addedOn }}
                            </template>
                        </span>
                        <span
                            v-if="
                                !performance.recording?.itemUrl &&
                                !performance.recording?.canDownload
                            "
                            >—</span
                        >
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-r10-grey-500 uppercase">
                        Planka kaart
                    </dt>
                    <dd class="text-r10-ink">
                        <a
                            v-if="performance.plankaCardUrl"
                            :href="performance.plankaCardUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            data-test="performance-planka-card-link"
                            class="inline-flex w-fit items-center gap-1.5 font-medium text-r10-navy underline underline-offset-2 hover:text-r10-orange-700"
                        >
                            <ExternalLink class="h-3.5 w-3.5" />
                            Ava Plankas
                        </a>
                        <span v-else>—</span>
                    </dd>
                </div>
            </dl>

            <!--
                Prose rather than another grid cell: read off the recording's
                subtitles once they are in, so a night with a video and no
                summary yet says it is waiting rather than showing a dash.
            -->
            <div
                v-if="performance.recording"
                data-test="performance-plot-summary"
            >
                <div class="text-xs font-bold text-r10-grey-500 uppercase">
                    Sisukokkuvõte
                </div>
                <p
                    v-if="performance.recording.plotSummary"
                    class="mt-1 whitespace-pre-line text-r10-ink"
                >
                    {{ performance.recording.plotSummary }}
                </p>
                <p v-else class="mt-1 text-[13px] text-r10-grey-500">
                    {{
                        performance.recording.plotSummaryState === 'abandoned'
                            ? 'Kokkuvõtet ei õnnestunud salvestuse subtiitritest koostada.'
                            : 'Kirjutatakse automaatselt, kui salvestuse subtiitrid on valmis.'
                    }}
                </p>
                <div
                    v-if="performance.recording.plotTags.length > 0"
                    class="mt-2 flex flex-wrap gap-1.5"
                    data-test="performance-plot-tags"
                >
                    <Badge
                        v-for="tag in performance.recording.plotTags"
                        :key="tag"
                        variant="secondary"
                    >
                        {{ tag }}
                    </Badge>
                </div>
                <!--
                    Read off speech-to-text by a model, so it says so: whoever
                    reads it should know it can be wrong, and that the crew can
                    put it right.
                -->
                <p
                    v-if="performance.recording.plotSummary"
                    class="mt-2 flex items-start gap-1.5 text-xs text-r10-grey-500"
                    data-test="performance-plot-summary-disclaimer"
                >
                    <Sparkles class="mt-px h-3.5 w-3.5 shrink-0" />
                    Kokkuvõte ja sildid on koostatud tehisaru abil
                    salvestuse automaatsetest subtiitritest ning võivad
                    sisaldada vigu.
                </p>
                <!-- The crew's alone; the server says who. -->
                <R10Button
                    v-if="performance.canRegeneratePlotSummary"
                    variant="outline"
                    size="sm"
                    class="mt-3"
                    :disabled="summaryRequest.processing"
                    data-test="regenerate-plot-summary-button"
                    @click="regeneratePlotSummary"
                >
                    <Sparkles class="h-3.5 w-3.5" />
                    Uus kokkuvõte
                </R10Button>
            </div>

            <RecordOriginFields
                :created-by="performance.createdBy"
                :created-at="performance.createdAt"
                :imported-at="performance.plankaImportedAt"
            />

            <PerformanceStaffList
                :format-id="formatId"
                :performance-id="performanceId"
                :staff="performance.staff ?? []"
                :roles="staffRoles"
                @changed="reload"
            />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <R10BackLink
                    v-if="can.openFormat"
                    :href="edit(formatId)"
                    label="Formaadi juurde"
                />
                <R10BackLink v-else :href="dashboard()" label="Töölauale" />

                <div class="flex flex-wrap items-center gap-2">
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
                        performance with no group members and no imported staff
                        has no reminder to send, the same way the reasoning log
                        is offered only where there is one.
                    -->
                    <R10Button
                        v-if="reminderRecipients.length > 0"
                        variant="outline"
                        size="sm"
                        data-test="send-reminder-button"
                        @click="reminderModalOpen = true"
                    >
                        <Mail class="h-3.5 w-3.5" />
                        Meeldetuletus
                    </R10Button>

                    <!--
                        Offered to the crew alone, and only where a board is
                        configured — the server says which, so the button is
                        never shown where the API would refuse it. A
                        performance that knows no card has nothing to read, so
                        the button stays in sight but switched off, and the
                        tooltip says why. The span takes the hover because a
                        disabled button takes no pointer events.
                    -->
                    <TooltipProvider
                        v-if="performance.canReimportFromPlanka"
                        :delay-duration="0"
                    >
                        <Tooltip :disabled="!!performance.plankaCardId">
                            <TooltipTrigger as-child>
                                <span
                                    class="inline-flex"
                                    :tabindex="
                                        performance.plankaCardId ? undefined : 0
                                    "
                                    data-test="refresh-from-planka-trigger"
                                >
                                    <R10Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            reimport.processing ||
                                            !performance.plankaCardId
                                        "
                                        data-test="refresh-from-planka-button"
                                        @click="refreshFromPlanka"
                                    >
                                        <RefreshCw
                                            class="h-3.5 w-3.5"
                                            :class="{
                                                'animate-spin':
                                                    reimport.processing,
                                            }"
                                        />
                                        Impordi
                                    </R10Button>
                                </span>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs">
                                <p>
                                    Etendusel pole Planka kaarti. Lisa kaardi ID
                                    etenduse muutmise vaates, siis saab selle
                                    Plankast uuesti importida.
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <R10Button
                        v-if="can.edit"
                        variant="outline"
                        size="sm"
                        data-test="edit-performance-button"
                        @click="editModalOpen = true"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        Muuda
                    </R10Button>

                    <R10Button
                        v-if="can.edit"
                        variant="danger"
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

        <!-- The page's own error above already says the load failed. -->
        <section v-if="!loadFailed" class="mt-9">
            <R10SectionHeader
                title="Tehnilised plaanid"
                lead="Selle etenduse jaoks esitatud plaanid."
                class="mb-4"
            >
                <!--
                    Opens the wizard with this night already chosen, the same
                    link a reminder carries. Offered only for a night still to
                    come; a draft one keeps the button in sight but switched
                    off, since the wizard would quietly drop the night and open
                    blank, and the tooltip says why. A disabled button takes no
                    pointer events, so the span takes the hover — and a link
                    cannot be disabled, so the switched-off one gets no href.
                -->
                <template
                    v-if="performance && isStillToCome(performance)"
                    #action
                >
                    <TooltipProvider :delay-duration="0">
                        <Tooltip :disabled="acceptsNewPlan(performance)">
                            <TooltipTrigger as-child>
                                <span
                                    class="inline-flex"
                                    :tabindex="
                                        acceptsNewPlan(performance)
                                            ? undefined
                                            : 0
                                    "
                                    data-test="new-technical-plan-trigger"
                                >
                                    <R10Button
                                        variant="primary"
                                        size="sm"
                                        :disabled="!acceptsNewPlan(performance)"
                                        :href="
                                            acceptsNewPlan(performance)
                                                ? planWizard({
                                                      query: {
                                                          performance:
                                                              performanceId,
                                                          step: 1,
                                                      },
                                                  })
                                                : undefined
                                        "
                                        data-test="new-technical-plan-button"
                                    >
                                        <Plus class="h-3.5 w-3.5" />
                                        Uus tehnikaplaan
                                    </R10Button>
                                </span>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs">
                                <p>
                                    Etendus on ülevaatamata. Muuda etenduse
                                    olekuks „Tulevane“, siis saab sellele
                                    tehnikaplaani lisada.
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </template>
            </R10SectionHeader>

            <TechnicalPlanTable
                :rows="technicalPlans"
                empty-text="Selle etenduse jaoks pole veel ühtegi plaani esitatud."
            />
        </section>

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
