<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import { store } from '@/routes/api/formats/performances/reminders';
import type { PerformanceReminderRecipient } from '@/types';

/**
 * Who to chase about a missing technical plan. The choice is the members of the
 * group playing the performance; everybody is ticked to begin with, because
 * chasing the whole group is the usual answer and unticking is easier than
 * hunting for names.
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
 * Start again from everybody every time the dialog opens: it is mounted with
 * the page rather than with the performance, so a narrowed choice made once
 * must not still be in force the next time somebody reaches for it.
 */
function selectEverybody(): void {
    form.resetAndClearErrors();
    form.user_ids = props.recipients.map((recipient) => recipient.id);
}

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
        description="Vali, kellele saata meeldetuletus puuduva tehnikaplaani kohta. Igaüks saab oma kirja, milles olev link logib ta ise sisse."
        submit-label="Saada"
        :processing="form.processing"
        test-id-prefix="send-reminder"
        @opened="selectEverybody"
        @submit="send"
    >
        <div class="flex flex-col gap-1.5">
            <span
                class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
            >
                Saajad
            </span>

            <label
                v-for="recipient in recipients"
                :key="recipient.id"
                class="flex cursor-pointer items-start gap-3 rounded-lg border-2 border-r10-grey-200 bg-white p-4"
            >
                <input
                    v-model="form.user_ids"
                    type="checkbox"
                    :value="recipient.id"
                    :data-test="`reminder-recipient-${recipient.id}`"
                    class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-r10-orange"
                />
                <span class="flex flex-col gap-0.5">
                    <span class="text-sm font-bold text-r10-ink">
                        {{ recipient.name }}
                    </span>
                    
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
