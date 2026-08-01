<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import ShowFormFields from '@/components/ShowFormFields.vue';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import { store } from '@/routes/api/shows';
import type { Show, ShowFormData, ShowTeamOption } from '@/types';

const props = defineProps<{ teams: ShowTeamOption[] }>();

const emit = defineEmits<{ created: [show: Show] }>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage();

const form = useHttp<ShowFormData>({
    team_id: null,
    name: '',
    description: '',
    planka_card_id: '',
});

/**
 * Start from the group the user is currently working in — it is the one a new
 * show almost always belongs to — but only if it is theirs to file under.
 */
function defaultTeamId(): number | null {
    const current = page.props.currentTeam?.id ?? null;

    return props.teams.some((team) => team.id === current) ? current : null;
}

function reset(): void {
    form.resetAndClearErrors();
    form.team_id = defaultTeamId();
}

async function save(): Promise<void> {
    try {
        const { data } = (await form.submit(store())) as { data: Show };

        emit('created', data);
        open.value = false;

        toast.success('Lavastus lisatud.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Lavastuse lisamine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <R10FormDialog
        v-model:open="open"
        title="Uus lavastus"
        description="Lisa lavastus, mida saab hiljem etendustega siduda."
        submit-label="Lisa lavastus"
        :processing="form.processing"
        test-id-prefix="create-show"
        @opened="reset"
        @submit="save"
    >
        <ShowFormFields
            v-model:team-id="form.team_id"
            v-model:name="form.name"
            v-model:description="form.description"
            v-model:planka-card-id="form.planka_card_id"
            :teams="teams"
            :errors="form.errors"
        />
    </R10FormDialog>
</template>
