<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import { store } from '@/routes/api/users';
import type { ManagedUser, NewManagedUserFormData } from '@/types';

const emit = defineEmits<{ created: [user: ManagedUser] }>();

const open = defineModel<boolean>('open', { required: true });

const form = useHttp<NewManagedUserFormData>({
    email: '',
    sendWelcome: true,
});

async function save(): Promise<void> {
    try {
        const { data } = (await form.submit(store())) as { data: ManagedUser };

        emit('created', data);
        open.value = false;

        toast.success(
            form.sendWelcome
                ? `Konto loodud. Sisselogimislink saadeti aadressile ${data.email}.`
                : 'Konto loodud. Tervituskirja ei saadetud.',
        );
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Konto loomine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <R10FormDialog
        v-model:open="open"
        title="Uus kasutaja"
        description="Konto luuakse ainult e-posti aadressiga. E-post jääb kinnitamata, kuni kasutaja esimest korda sisselogimislingiga sisse logib."
        submit-label="Loo konto"
        :processing="form.processing"
        test-id-prefix="create-user"
        @opened="form.resetAndClearErrors()"
        @submit="save"
    >
        <R10Input
            v-model="form.email"
            label="E-post"
            type="email"
            required
            placeholder="nimi@naide.ee"
            :error="form.errors.email"
            error-test-id="create-user-email-error"
        />

        <label
            class="flex cursor-pointer items-start gap-3 rounded-lg border-2 border-r10-grey-200 bg-white p-4"
        >
            <input
                v-model="form.sendWelcome"
                type="checkbox"
                data-test="create-user-send-welcome"
                class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-r10-orange"
            />
            <span class="flex flex-col gap-0.5">
                <span class="text-sm font-bold text-r10-ink">
                    Saada tervituskiri
                </span>
                <span class="text-[13px] text-r10-grey-500">
                    Kiri sisaldab ühekordset sisselogimislinki, mis kehtib kolm
                    päeva. Ilma selleta saab kasutaja sisselogimislingi ise
                    küsida sisselogimislehelt.
                </span>
            </span>
        </label>
    </R10FormDialog>
</template>
