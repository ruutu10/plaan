<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import { store } from '@/routes/api/teams/members';
import type {
    AddTeamMemberFormData,
    ManagedTeamMember,
    RoleOption,
} from '@/types';

const props = defineProps<{ teamId: number; roles: RoleOption[] }>();

const emit = defineEmits<{ added: [member: ManagedTeamMember] }>();

const open = defineModel<boolean>('open', { required: true });

const form = useHttp<AddTeamMemberFormData>({
    email: '',
    role: 'member',
});

const roleOptions = computed(() =>
    props.roles.map((role) => ({ value: role.value, label: role.label })),
);

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
    <R10FormDialog
        v-model:open="open"
        title="Lisa liige"
        description="Lisa tiimi olemasolev kasutaja. Kellel kontot veel pole, tuleb kutsuda kutsega."
        submit-label="Lisa liige"
        :processing="form.processing"
        test-id-prefix="add-member"
        @opened="form.resetAndClearErrors()"
        @submit="save"
    >
        <R10Input
            v-model="form.email"
            label="E-post"
            type="email"
            required
            placeholder="nimi@näide.ee"
            :error="form.errors.email"
            error-test-id="add-member-email-error"
        />

        <R10Select
            v-model="form.role"
            label="Roll"
            required
            :options="roleOptions"
            :error="form.errors.role"
            data-test="add-member-role-select"
        />
    </R10FormDialog>
</template>
