<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import ShowFormFields from '@/components/ShowFormFields.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store } from '@/routes/api/shows';
import type { Show, ShowFormData, ShowTeamOption } from '@/types';

type Props = {
    teams: ShowTeamOption[];
};

const props = defineProps<Props>();

const emit = defineEmits<{ created: [show: Show] }>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage();

const form = useHttp<ShowFormData>({
    team_id: null,
    name: '',
    description: '',
});

/**
 * Start from the group the user is currently working in — it is the one a new
 * show almost always belongs to — but only if it is theirs to file under.
 */
function defaultTeamId(): number | null {
    const current = page.props.currentTeam?.id ?? null;

    return props.teams.some((team) => team.id === current) ? current : null;
}

function handleOpenChange(value: boolean): void {
    open.value = value;

    if (value) {
        form.resetAndClearErrors();
        form.team_id = defaultTeamId();
    }
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
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <form class="flex flex-col gap-6" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle
                        class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                    >
                        Uus lavastus
                    </DialogTitle>
                    <DialogDescription class="text-[15px] text-r10-grey-500">
                        Lisa lavastus, mida saab hiljem etendustega siduda.
                    </DialogDescription>
                </DialogHeader>

                <ShowFormFields
                    v-model:team-id="form.team_id"
                    v-model:name="form.name"
                    v-model:description="form.description"
                    :teams="teams"
                    :errors="form.errors"
                    id-prefix="new-show"
                />

                <div class="flex items-center justify-end gap-3">
                    <R10Button
                        variant="outline"
                        :disabled="form.processing"
                        data-test="create-show-cancel"
                        @click="handleOpenChange(false)"
                    >
                        Loobu
                    </R10Button>

                    <R10Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="create-show-submit"
                    >
                        Lisa lavastus
                    </R10Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
