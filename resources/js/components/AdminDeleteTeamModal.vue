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
import { destroy } from '@/routes/api/teams';
import type { ManagedTeam } from '@/types';

type Props = {
    team: ManagedTeam | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

const http = useHttp();

async function remove(): Promise<void> {
    if (!props.team) {
        return;
    }

    try {
        await http.submit(destroy(props.team.id));

        emit('deleted');
        open.value = false;

        toast.success('Tiim kustutatud.');
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
                    Kustuta tiim
                </DialogTitle>
                <DialogDescription class="text-[15px] text-r10-grey-500">
                    Kas kustutada tiim „{{ team?.name }}“? See kaob nimekirjast,
                    kuid jääb andmebaasi alles.
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="
                    (team?.memberCount ?? 0) > 0 || (team?.showCount ?? 0) > 0
                "
                data-test="team-delete-warning"
                class="flex gap-3 rounded-lg border-2 border-r10-orange bg-r10-orange-100 p-4 text-[14px] text-r10-grey-700"
            >
                <TriangleAlert
                    class="mt-0.5 h-5 w-5 shrink-0 text-r10-orange-700"
                />
                <span>
                    Tiimi {{ team?.memberCount ?? 0 }} liiget kaotavad ligipääsu
                    ja viiakse üle oma isiklikku tiimi. Tiimi
                    {{ team?.showCount ?? 0 }} lavastust jäävad alles, kuid ilma
                    tiimita neid enam ei halda.
                </span>
            </p>

            <div class="flex items-center justify-end gap-3">
                <R10Button
                    variant="outline"
                    :disabled="http.processing"
                    data-test="team-delete-cancel"
                    @click="open = false"
                >
                    Loobu
                </R10Button>

                <button
                    type="button"
                    :disabled="http.processing"
                    data-test="team-delete-confirm"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-r10-error px-6 py-3 font-r10-body text-sm font-bold tracking-[0.04em] text-white uppercase transition hover:opacity-90 disabled:pointer-events-none disabled:opacity-50"
                    @click="remove"
                >
                    Kustuta
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
