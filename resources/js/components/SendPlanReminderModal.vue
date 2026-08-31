<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import { store } from '@/routes/api/formats/performances/reminders';
import type { PerformanceReminderRecipient } from '@/types';

/**
 * Who to chase about a missing technical plan. The choice is the whole of the
 * group playing the performance; ticked to begin with are the members the
 * Planka card casts as esinejad that night, because they are the ones who owe
 * the plan. A night nothing was imported for starts with nobody ticked rather
 * than with everybody.
 *
 * Each person picked gets a letter of their own carrying a link that signs them
 * in, so nobody is copied on anybody else's.
 */
const props = defineProps<{
    formatId: number;
    performanceId: number;
    recipients: PerformanceReminderRecipient[];
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useHttp<{ user_ids: number[] }>({ user_ids: [] });

/**
 * Start again from the night's cast every time the dialog opens: it is mounted
 * with the page rather than with the performance, so a choice made once must
 * not still be in force the next time somebody reaches for it.
 */
function selectTheCast(): void {
    form.resetAndClearErrors();
    form.user_ids = props.recipients
        .filter((recipient) => recipient.staffedAsPerformer)
        .map((recipient) => recipient.id);
}

/** Everybody the group has, for a night the whole of it should hear about. */
function selectEverybody(): void {
    form.user_ids = props.recipients.map((recipient) => recipient.id);
}

/** A clean sheet, for picking one or two people out by hand. */
function selectNobody(): void {
    form.user_ids = [];
}

const everybodyChosen = computed(
    () =>
        props.recipients.length > 0 &&
        form.user_ids.length === props.recipients.length,
);

async function send(): Promise<void> {
    try {
        const { sent } = (await form.submit(
            store([props.formatId, props.performanceId]),
        )) as { sent: number };

        open.value = false;

        toast.success(
            sent === 1
                ? 'Meeldetuletus saadetud.'
                : `Meeldetuletus saadetud (${sent}).`,
        );
    } catch {
        // A refused send leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Meeldetuletuse saatmine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <R10FormDialog
        v-model:open="open"
        title="Saada meeldetuletus"
        description="Vali, kellele saata meeldetuletus puuduva tehnikaplaani kohta. Ette on märgitud selle etenduse esinejad. Igaüks saab oma kirja, milles olev link logib ta ise sisse."
        submit-label="Saada"
        :processing="form.processing"
        test-id-prefix="send-reminder"
        @opened="selectTheCast"
        @submit="send"
    >
        <div class="flex flex-col gap-1.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                >
                    Saajad
                </span>

                <!--
                    Plain buttons, not submits: the whole dialog is a form, so a
                    bare <button> in it would send the reminder instead of
                    changing the ticks.
                -->
                <span class="flex items-center gap-3 text-xs">
                    <button
                        type="button"
                        :disabled="everybodyChosen"
                        class="cursor-pointer text-r10-grey-500 underline transition hover:text-r10-orange-700 disabled:pointer-events-none disabled:opacity-40"
                        data-test="reminder-select-all"
                        @click="selectEverybody"
                    >
                        Vali kõik
                    </button>

                    <button
                        type="button"
                        :disabled="form.user_ids.length === 0"
                        class="cursor-pointer text-r10-grey-500 underline transition hover:text-r10-orange-700 disabled:pointer-events-none disabled:opacity-40"
                        data-test="reminder-select-none"
                        @click="selectNobody"
                    >
                        Tühista valik
                    </button>
                </span>
            </div>

            <label
                v-for="recipient in recipients"
                :key="recipient.id"
                class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-r10-grey-200 bg-white p-4"
            >
                <input
                    v-model="form.user_ids"
                    type="checkbox"
                    :value="recipient.id"
                    :data-test="`reminder-recipient-${recipient.id}`"
                    class="h-4 w-4 shrink-0 cursor-pointer accent-r10-orange"
                />
                <span class="text-sm font-bold text-r10-ink">
                    {{ recipient.name }}
                </span>
            </label>

            <span
                v-if="form.errors.user_ids"
                class="text-xs font-medium text-r10-orange-700"
                data-test="reminder-recipients-error"
            >
                {{ form.errors.user_ids }}
            </span>
        </div>
    </R10FormDialog>
</template>
