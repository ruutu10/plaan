<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import { store } from '@/routes/api/teams';
import type { ManagedTeam, ManagedTeamFormData } from '@/types';

const emit = defineEmits<{ created: [team: ManagedTeam] }>();

const open = defineModel<boolean>('open', { required: true });

const form = useHttp<ManagedTeamFormData>({ name: '' });

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
    <R10FormDialog
        v-model:open="open"
        title="Uus tiim"
        description="Loo tiim ja lisa sellele hiljem liikmeid. Tiimi omanik oled sina."
        submit-label="Lisa tiim"
        :processing="form.processing"
        test-id-prefix="create-team"
        @opened="form.resetAndClearErrors()"
        @submit="save"
    >
        <R10Input
            v-model="form.name"
            label="Nimi"
            required
            placeholder="Tiimi nimi"
            :error="form.errors.name"
            error-test-id="create-team-name-error"
        />
    </R10FormDialog>
</template>
