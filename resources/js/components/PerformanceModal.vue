<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import { store, update } from '@/routes/api/shows/performances';
import type { Performance, ShowTeamOption } from '@/types';

/**
 * Adds a performance to a show, or corrects one — the two differ only in where
 * the form is posted and what it starts from, so one dialog serves both.
 */
const props = defineProps<{
    showId: number;
    /** The performance being corrected, or null when a new one is being added. */
    performance: Performance | null;
    /** The groups a performance may be handed to. */
    teams: ShowTeamOption[];
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
 * the show's own. An empty string rather than null so the select can offer it
 * as an ordinary option to come back to.
 */
const SHOW_S_OWN_TEAM = '';

// The dated fields are held as the strings the inputs deal in; the duration and
// the team become numbers (or nothing at all) on their way out.
const form = useHttp({
    title: '',
    team_id: SHOW_S_OWN_TEAM as string | number,
    date: '',
    start_time: '',
    duration: '',
    is_draft: false,
}).transform((data) => ({
    title: data.title,
    team_id: data.team_id === SHOW_S_OWN_TEAM ? null : Number(data.team_id),
    date: data.date,
    start_time: data.start_time,
    duration: data.duration === '' ? null : Number(data.duration),
    is_draft: data.is_draft,
}));

const isEditing = computed(() => props.performance !== null);

/**
 * The groups on offer, led by the option that hands the performance back to the
 * show's own group — the ordinary case, and the one a mis-set team is undone
 * with.
 */
const teamOptions = computed(() => [
    { value: SHOW_S_OWN_TEAM, label: '— lavastuse enda tiim —' },
    ...props.teams.map((team) => ({ value: team.id, label: team.name })),
]);

/**
 * Fill the form as the dialog opens, so it never shows the previous
 * performance's values for a beat before the right ones land.
 */
function fill(): void {
    form.clearErrors();
    form.title = props.performance?.title ?? '';
    form.team_id = props.performance?.teamId ?? SHOW_S_OWN_TEAM;
    form.date = props.performance?.date ?? '';
    form.start_time = props.performance?.startTime ?? USUAL_START_TIME;
    form.duration = props.performance?.duration?.toString() ?? '';
    // A performance added here is vouched for by the adding; only an imported
    // one starts out waiting to be reviewed.
    form.is_draft = props.performance?.isDraft ?? false;
}

async function save(): Promise<void> {
    const target = props.performance
        ? update([props.showId, props.performance.id])
        : store(props.showId);

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
        description="Etendus on lavastuse üks kuupäevaga mängukord."
        submit-label="Salvesta"
        :processing="form.processing"
        test-id-prefix="performance"
        @opened="fill"
        @submit="save"
    >
        <R10Input
            v-model="form.title"
            label="Etteaste nimi"
            hint="Täida ainult siis, kui samal õhtul astub üles mitu truppi — nt õppelaval. Muidu jääb etendus lavastuse enda nime alla."
            placeholder="Nt Märtu10"
            data-test="performance-title-input"
            :error="form.errors.title"
        />

        <R10Select
            v-model="form.team_id"
            label="Esineja tiim"
            hint="Trupp, kes selle etteaste laval teeb. Jäta täitmata, kui esineb lavastuse enda tiim."
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

        <div class="flex flex-col gap-1.5">
            <label
                class="flex cursor-pointer items-start gap-3 rounded-lg border-2 border-r10-grey-200 bg-white p-4"
            >
                <input
                    v-model="form.is_draft"
                    type="checkbox"
                    data-test="performance-draft-toggle"
                    class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-r10-orange"
                />
                <span class="flex flex-col gap-0.5">
                    <span
                        class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                    >
                        Ülevaatamata
                    </span>
                    <span class="text-xs text-r10-grey-500">
                        Ülevaatamata etendus on mustand või mitte kinnitatud
                        kuupäev. Seda ei pakuta tehnikaplaani koostajale
                        valikuna. Imporditud etendused ootavad siin ülevaatamist
                        — eemalda linnuke, kui kuupäev on õige.
                    </span>
                </span>
            </label>
            <span
                v-if="form.errors.is_draft"
                class="text-xs font-medium text-r10-orange-700"
            >
                {{ form.errors.is_draft }}
            </span>
        </div>
    </R10FormDialog>
</template>
