<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
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
import { store } from '@/routes/api/teams';
import type { ManagedTeam, ManagedTeamFormData } from '@/types';

const emit = defineEmits<{ created: [team: ManagedTeam] }>();

const open = defineModel<boolean>('open', { required: true });

const form = useHttp<ManagedTeamFormData>({ name: '' });

function handleOpenChange(value: boolean): void {
    open.value = value;

    if (value) {
        form.resetAndClearErrors();
    }
}

async function save(): Promise<void> {
    try {
        const { data } = (await form.submit(store())) as { data: ManagedTeam };

        emit('created', data);
        open.value = false;

        toast.success('Tiim loodud. Sina oled selle omanik.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Tiimi loomine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <form class="flex flex-col gap-6" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle
                        class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                    >
                        Uus tiim
                    </DialogTitle>
                    <DialogDescription class="text-[15px] text-r10-grey-500">
                        Loo tiim ja lisa sellele hiljem liikmeid. Tiimi omanik
                        oled sina.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-1.5">
                    <R10Input
                        v-model="form.name"
                        label="Nimi"
                        required
                        placeholder="Tiimi nimi"
                    />
                    <span
                        v-if="form.errors.name"
                        data-test="create-team-name-error"
                        class="text-xs font-medium text-r10-orange-700"
                    >
                        {{ form.errors.name }}
                    </span>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <R10Button
                        variant="outline"
                        :disabled="form.processing"
                        data-test="create-team-cancel"
                        @click="handleOpenChange(false)"
                    >
                        Loobu
                    </R10Button>

                    <R10Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="create-team-submit"
                    >
                        Lisa tiim
                    </R10Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
