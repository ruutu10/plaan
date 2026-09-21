<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import JellyfinRecordingField from '@/components/JellyfinRecordingField.vue';
import PlankaCardField from '@/components/PlankaCardField.vue';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import { formatLocalTime, toLocalDateInputValue } from '@/lib/date';
import { performanceStatusOptions } from '@/lib/performanceStatus';
import { store, update } from '@/routes/api/formats/performances';
import type {
    FormatOption,
    FormatTeamOption,
    Performance,
    PerformanceStatus,
} from '@/types';

/**
 * Adds a performance to a format, or corrects one — the two differ only in where
 * the form is posted and what it starts from, so one dialog serves both.
 */
const props = defineProps<{
    /**
     * The format a new performance is added to, when the dialog is opened from
     * that format's own page. Null when the dialog itself is where the format is
     * chosen — see `formats` — which only ever happens for a new performance;
     * one being corrected already knows its format.
     */
    formatId: number | null;
    /** The performance being corrected, or null when a new one is being added. */
    performance: Performance | null;
    /** The groups a performance may be handed to. */
    teams: FormatTeamOption[];
    /** Offered only when `formatId` is null: the formats to choose from. */
    formats?: FormatOption[];
}>();

const emit = defineEmits<{ saved: [] }>();

const open = defineModel<boolean>('open', { required: true });

/**
 * The hour a new performance is offered at. The house's own default lives
 * server-side (`config/performance.php`), which is what an empty start time
 * falls back to; this only spares whoever is adding a performance from typing
 * the usual answer.
 */
const USUAL_START_TIME = '19:00';

/**
 * The value standing for "no group of its own", which leaves the performance to
 * the format's own. An empty string rather than null so the select can offer it
 * as an ordinary option to come back to.
 */
const FORMAT_S_OWN_TEAM = '';

// The dated fields are held as the strings the inputs deal in; the duration and
// the team become numbers (or nothing at all) on their way out. `format_id` never
// reaches the server as a field of its own — it only picks the URL the rest is
// posted to, see `save()` below.
const form = useHttp({
    format_id: null as number | null,
    title: '',
    team_id: FORMAT_S_OWN_TEAM as string | number,
    date: '',
    start_time: '',
    duration: '',
    // Typed loosely like `team_id` above: the select hands back whatever the
    // DOM gives it, and the transform below narrows it on the way out.
    status: 'upcoming' as string | number,
    planka_card_id: '',
    recording_url: '',
}).transform((data) => ({
    title: data.title,
    team_id: data.team_id === FORMAT_S_OWN_TEAM ? null : Number(data.team_id),
    date: data.date,
    start_time: data.start_time,
    duration: data.duration === '' ? null : Number(data.duration),
    status: data.status as PerformanceStatus,
    planka_card_id: data.planka_card_id,
    // Left out entirely unless the server said this reader may set it — a form
    // that posted it blank would clear a link somebody else put there, and the
    // field is not on the screen to be cleared. The server holds the same line
    // on its own; this only keeps the request honest.
    ...(canLinkRecording.value ? { recording_url: data.recording_url } : {}),
}));

const isEditing = computed(() => props.performance !== null);

/**
 * Whether this reader may say where the night's recording is. Never on a new
 * performance: a video of an evening nobody has played yet is not a thing.
 */
const canLinkRecording = computed(
    () => props.performance?.canLinkRecording ?? false,
);

/** Whether the dialog itself offers the choice of format — see `formatId`. */
const choosesFormat = computed(() => props.formatId === null);

const formatOptions = computed(
    () =>
        props.formats?.map((format) => ({
            value: format.id,
            label: format.name,
        })) ?? [],
);

/** The standings a performance can be put in — see `@/lib/performanceStatus`. */
const statusOptions = performanceStatusOptions();

/**
 * The groups on offer, led by the option that hands the performance back to the
 * format's own group — the ordinary case, and the one a mis-set team is undone
 * with.
 */
const teamOptions = computed(() => [
    { value: FORMAT_S_OWN_TEAM, label: '— formaadi enda tiim —' },
    ...props.teams.map((team) => ({ value: team.id, label: team.name })),
]);

/**
 * Fill the form as the dialog opens, so it never shows the previous
 * performance's values for a beat before the right ones land.
 */
function fill(): void {
    form.clearErrors();
    // Defaults to the first format on offer, so a choice is always in place —
    // the button that opens this dialog is withheld when there is none to
    // default to.
    form.format_id =
        props.performance?.formatId ??
        props.formatId ??
        props.formats?.[0]?.id ??
        null;
    form.title = props.performance?.title ?? '';
    form.team_id = props.performance?.teamId ?? FORMAT_S_OWN_TEAM;
    form.date = props.performance
        ? toLocalDateInputValue(props.performance.startsAt)
        : '';
    form.start_time = props.performance
        ? formatLocalTime(props.performance.startsAt)
        : USUAL_START_TIME;
    form.duration = props.performance?.duration?.toString() ?? '';
    // A performance added here is vouched for by the adding; only an imported
    // one starts out waiting to be reviewed.
    form.status = props.performance?.status ?? 'upcoming';
    form.planka_card_id = props.performance?.plankaCardId ?? '';
    form.recording_url = props.performance?.recording?.url ?? '';
}

async function save(): Promise<void> {
    // The performance being corrected already knows its own format; a new one
    // takes whichever format the dialog fixed, or the one chosen inside it.
    const formatId =
        props.performance?.formatId ?? props.formatId ?? form.format_id!;

    const target = props.performance
        ? update([formatId, props.performance.id])
        : store(formatId);

    try {
        await form.submit(target);

        emit('saved');
        open.value = false;

        toast.success(
            isEditing.value ? 'Etendus salvestatud.' : 'Etendus lisatud.',
        );
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Salvestamine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <R10FormDialog
        v-model:open="open"
        :title="isEditing ? 'Muuda etendust' : 'Uus etendus'"
        description="Etendus on formaadi üks kuupäevaga mängukord."
        submit-label="Salvesta"
        :processing="form.processing"
        test-id-prefix="performance"
        @opened="fill"
        @submit="save"
    >
        <R10Select
            v-if="choosesFormat"
            v-model="form.format_id"
            label="Formaat"
            hint="Milline formaat see etendus on."
            required
            :options="formatOptions"
            :error="form.errors.format_id"
            data-test="performance-format-select"
        />

        <R10Input
            v-model="form.title"
            label="Etteaste nimi"
            hint="Täida ainult siis, kui samal õhtul astub üles mitu truppi — nt õppelaval. Muidu jääb etendus formaadi enda nime alla."
            placeholder="Nt Märtu10"
            data-test="performance-title-input"
            :error="form.errors.title"
        />

        <R10Select
            v-model="form.team_id"
            label="Esineja tiim"
            hint="Trupp, kes selle etteaste laval teeb. Jäta täitmata, kui esineb formaadi enda tiim."
            :options="teamOptions"
            :error="form.errors.team_id"
            data-test="performance-team-select"
        />

        <R10Input
            v-model="form.date"
            type="date"
            label="Kuupäev"
            required
            :error="form.errors.date"
        />

        <R10Input
            v-model="form.start_time"
            type="time"
            label="Algusaeg"
            hint="Mis kell etendus laval algab. Sellest arvestatakse tehnikaplaani meeldetuletused."
            required
            :error="form.errors.start_time"
        />

        <R10Input
            v-model="form.duration"
            type="number"
            label="Kestus (min)"
            hint="Etenduse eeldatav pikkus minutites"
            placeholder="90"
            :error="form.errors.duration"
        />

        <PlankaCardField
            v-model="form.planka_card_id"
            :card-url="performance?.plankaCardUrl"
            :error="form.errors.planka_card_id"
        />

        <JellyfinRecordingField
            v-if="canLinkRecording"
            v-model="form.recording_url"
            :item-url="performance?.recording?.itemUrl"
            :synced-at="performance?.recording?.syncedAt"
            :sync-error="performance?.recording?.syncError"
            :error="form.errors.recording_url"
        />

        <R10Select
            v-model="form.status"
            label="Olek"
            hint="Ülevaatamata etendust ei pakuta tehnikaplaani koostajale valikuna — imporditud etendused ootavad siin ülevaatamist. Arhiveeritud on ära mängitud õhtu; selle märgib süsteem ise iga nädal."
            :options="statusOptions"
            data-test="performance-status-select"
            :error="form.errors.status"
            error-test-id="performance-status-error"
        />
    </R10FormDialog>
</template>
