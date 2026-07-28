<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store, update } from '@/routes/api/shows/performances';
import type { Performance } from '@/types';

/**
 * Adds a performance to a show, or corrects one — the two differ only in where the
 * form is posted and what it starts from, so one dialog serves both.
 */
type Props = {
    showId: number;
    /** The performance being corrected, or null when a new one is being added. */
    performance: Performance | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{ saved: [] }>();

const open = defineModel<boolean>('open', { required: true });

// The dated fields are held as the strings the inputs deal in; the duration
// becomes a number (or nothing at all) on its way out.
const form = useHttp({
    date: '',
    duration: '',
    is_draft: false,
}).transform((data) => ({
    date: data.date,
    duration: data.duration === '' ? null : Number(data.duration),
    is_draft: data.is_draft,
}));

const isEditing = computed(() => props.performance !== null);

// Fill the form as the dialog opens, so it never shows the previous performance's
// values for a beat before the right ones land.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.date = props.performance?.date ?? '';
    form.duration = props.performance?.duration?.toString() ?? '';
    // A performance added here is vouched for by the adding; only an imported
    // one starts out waiting to be reviewed.
    form.is_draft = props.performance?.isDraft ?? false;
});

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
    <Dialog :open="open" @update:open="open = $event">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <form class="flex flex-col gap-6" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle
                        class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                    >
                        {{ isEditing ? 'Muuda etendust' : 'Uus etendus' }}
                    </DialogTitle>
                    <DialogDescription class="text-[15px] text-r10-grey-500">
                        Etendus on lavastuse üks kuupäevaga mängukord.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-1.5">
                    <R10Input
                        v-model="form.date"
                        type="date"
                        label="Kuupäev"
                        required
                    />
                    <span
                        v-if="form.errors.date"
                        class="text-xs font-medium text-r10-orange-700"
                    >
                        {{ form.errors.date }}
                    </span>
                </div>

                <div class="flex flex-col gap-1.5">
                    <R10Input
                        v-model="form.duration"
                        type="number"
                        label="Kestus (min)"
                        hint="Vabatahtlik. Etenduse eeldatav pikkus minutites."
                        placeholder="90"
                    />
                    <span
                        v-if="form.errors.duration"
                        class="text-xs font-medium text-r10-orange-700"
                    >
                        {{ form.errors.duration }}
                    </span>
                </div>

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
                                Ülevaatamata etendust ei pakuta tehnikaplaani
                                koostajale. Imporditud etendused ootavad siin
                                ülevaatamist — eemalda linnuke, kui kuupäev on
                                õige.
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

                <div class="flex items-center justify-end gap-3">
                    <R10Button
                        variant="outline"
                        :disabled="form.processing"
                        data-test="performance-cancel"
                        @click="open = false"
                    >
                        Loobu
                    </R10Button>

                    <R10Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="performance-submit"
                    >
                        Salvesta
                    </R10Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
