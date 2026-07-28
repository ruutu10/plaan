<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import R10Button from '@/components/technical-plan/R10Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/api/teams/members';
import type { ManagedTeamMember } from '@/types';

type Props = {
    teamId: number;
    member: ManagedTeamMember | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{ removed: [] }>();

const open = defineModel<boolean>('open', { required: true });

const http = useHttp();

async function remove(): Promise<void> {
    if (!props.member) {
        return;
    }

    try {
        await http.submit(destroy([props.teamId, props.member.id]));

        emit('removed');
        open.value = false;

        toast.success('Liige eemaldatud.');
    } catch {
        toast.error('Eemaldamine ebaõnnestus. Proovi uuesti.');
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
                    Eemalda liige
                </DialogTitle>
                <DialogDescription class="text-[15px] text-r10-grey-500">
                    Kas eemaldada {{ member?.name }} tiimist? Konto ise jääb
                    alles ja ta saab tiimi hiljem tagasi lisada.
                </DialogDescription>
            </DialogHeader>

            <div class="flex items-center justify-end gap-3">
                <R10Button
                    variant="outline"
                    :disabled="http.processing"
                    data-test="remove-member-cancel"
                    @click="open = false"
                >
                    Loobu
                </R10Button>

                <button
                    type="button"
                    :disabled="http.processing"
                    data-test="remove-member-confirm"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-r10-error px-6 py-3 font-r10-body text-sm font-bold tracking-[0.04em] text-white uppercase transition hover:opacity-90 disabled:pointer-events-none disabled:opacity-50"
                    @click="remove"
                >
                    Eemalda
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
