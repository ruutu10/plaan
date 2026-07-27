<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { toast } from 'vue-sonner';
import R10Button from '@/components/technical-plan/R10Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatEstonianDate } from '@/lib/date';
import { destroy } from '@/routes/api/shows/performances';
import type { Performance } from '@/types';

type Props = {
    showId: number;
    performance: Performance | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

const http = useHttp();

async function remove(): Promise<void> {
    if (!props.performance) {
        return;
    }

    try {
        await http.submit(destroy([props.showId, props.performance.id]));

        emit('deleted');
        open.value = false;

        toast.success('Etendus kustutatud.');
    } catch {
        toast.error('Kustutamine ebaõnnestus. Proovi uuesti.');
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="open = $event">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <DialogHeader>
                <DialogTitle
                    class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                >
                    Kustuta etendus
                </DialogTitle>
                <DialogDescription class="text-[15px] text-r10-grey-500">
                    Kas kustutada
                    {{ formatEstonianDate(performance?.date) }} etendus? Seda ei
                    saa tagasi võtta.
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="(performance?.technicalPlanCount ?? 0) > 0"
                data-test="performance-delete-warning"
                class="flex gap-3 rounded-lg border-2 border-r10-orange bg-r10-orange-100 p-4 text-[14px] text-r10-grey-700"
            >
                <TriangleAlert
                    class="mt-0.5 h-5 w-5 shrink-0 text-r10-orange-700"
                />
                <span>
                    Sellele etendusele on esitatud
                    {{ performance?.technicalPlanCount }} tehnikaplaani. Plaanid
                    jäävad alles, kuid etendus kaob nende juurest.
                </span>
            </p>

            <div class="flex items-center justify-end gap-3">
                <R10Button
                    variant="outline"
                    :disabled="http.processing"
                    data-test="performance-delete-cancel"
                    @click="open = false"
                >
                    Loobu
                </R10Button>

                <button
                    type="button"
                    :disabled="http.processing"
                    data-test="performance-delete-confirm"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-r10-error px-6 py-3 font-r10-body text-sm font-bold tracking-[0.04em] text-white uppercase transition hover:opacity-90 disabled:pointer-events-none disabled:opacity-50"
                    @click="remove"
                >
                    Kustuta
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
