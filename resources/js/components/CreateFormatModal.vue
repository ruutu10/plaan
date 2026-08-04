<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import FormatFormFields from '@/components/FormatFormFields.vue';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import { store } from '@/routes/api/formats';
import type { Format, FormatFormData, FormatTeamOption } from '@/types';

const props = defineProps<{ teams: FormatTeamOption[] }>();

const emit = defineEmits<{ created: [format: Format] }>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage();

const form = useHttp<FormatFormData>({
    team_id: null,
    name: '',
    description: '',
});

/**
 * Start from the group the user is currently working in — it is the one a new
 * format almost always belongs to — but only if it is theirs to file under.
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
        const { data } = (await form.submit(store())) as { data: Format };

        emit('created', data);
        open.value = false;

        toast.success('Formaat lisatud.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Formaadi lisamine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <R10FormDialog
        v-model:open="open"
        title="Uus formaat"
        description="Lisa formaat, mida saab hiljem etendustega siduda."
        submit-label="Lisa formaat"
        :processing="form.processing"
        test-id-prefix="create-format"
        @opened="reset"
        @submit="save"
    >
        <FormatFormFields
            v-model:team-id="form.team_id"
            v-model:name="form.name"
            v-model:description="form.description"
            :teams="teams"
            :errors="form.errors"
        />
    </R10FormDialog>
</template>
