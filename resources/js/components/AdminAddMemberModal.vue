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
import { store } from '@/routes/api/teams/members';
import type {
    AddTeamMemberFormData,
    ManagedTeamMember,
    RoleOption,
} from '@/types';

type Props = {
    teamId: number;
    roles: RoleOption[];
};

const props = defineProps<Props>();

const emit = defineEmits<{ added: [member: ManagedTeamMember] }>();

const open = defineModel<boolean>('open', { required: true });

const form = useHttp<AddTeamMemberFormData>({
    email: '',
    role: 'member',
});

function handleOpenChange(value: boolean): void {
    open.value = value;

    if (value) {
        form.resetAndClearErrors();
    }
}

async function save(): Promise<void> {
    try {
        const { data } = (await form.submit(store(props.teamId))) as {
            data: ManagedTeamMember;
        };

        emit('added', data);
        open.value = false;

        toast.success('Liige lisatud.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Liikme lisamine ebaõnnestus. Proovi uuesti.');
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
                        Lisa liige
                    </DialogTitle>
                    <DialogDescription class="text-[15px] text-r10-grey-500">
                        Lisa tiimi olemasolev kasutaja. Kellel kontot veel pole,
                        tuleb kutsuda kutsega.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-1.5">
                    <R10Input
                        v-model="form.email"
                        label="E-post"
                        type="email"
                        required
                        placeholder="nimi@näide.ee"
                    />
                    <span
                        v-if="form.errors.email"
                        data-test="add-member-email-error"
                        class="text-xs font-medium text-r10-orange-700"
                    >
                        {{ form.errors.email }}
                    </span>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label
                        for="add-member-role"
                        class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                    >
                        Roll
                        <span class="text-r10-orange">*</span>
                    </label>
                    <select
                        id="add-member-role"
                        v-model="form.role"
                        data-test="add-member-role-select"
                        class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange"
                    >
                        <option
                            v-for="role in roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.role"
                        class="text-xs font-medium text-r10-orange-700"
                    >
                        {{ form.errors.role }}
                    </span>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <R10Button
                        variant="outline"
                        :disabled="form.processing"
                        data-test="add-member-cancel"
                        @click="handleOpenChange(false)"
                    >
                        Loobu
                    </R10Button>

                    <R10Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="add-member-submit"
                    >
                        Lisa liige
                    </R10Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
