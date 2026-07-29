<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/teams';
import type { Team } from '@/types';

const props = defineProps<{ team: Team }>();

const open = defineModel<boolean>('open', { required: true });

/** Deleting is held behind typing the team's name, so it cannot be a slip. */
const confirmationName = ref('');
/** Bumped to remount the form, which is how a closed dialog forgets what was typed. */
const formKey = ref(0);

const canDelete = computed(() => confirmationName.value === props.team.name);

function change(value: boolean): void {
    open.value = value;

    if (!value) {
        confirmationName.value = '';
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="change">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <Form
                :key="formKey"
                v-bind="destroy.form(team.slug)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                @success="change(false)"
            >
                <DialogHeader>
                    <DialogTitle
                        class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                    >
                        Kustuta tiim
                    </DialogTitle>
                    <DialogDescription class="text-[15px] text-r10-grey-500">
                        Kas kustutada tiim „{{ team.name }}“?
                    </DialogDescription>
                </DialogHeader>

                <p
                    class="flex gap-3 rounded-lg border-2 border-r10-orange bg-r10-orange-100 p-4 text-[14px] text-r10-grey-700"
                >
                    <TriangleAlert
                        class="mt-0.5 h-5 w-5 shrink-0 text-r10-orange-700"
                    />
                    <span>
                        Liikmed kaotavad tiimile ligipääsu ja viiakse üle oma
                        isiklikku tiimi. Seda ei saa tagasi võtta.
                    </span>
                </p>

                <R10Input
                    v-model="confirmationName"
                    name="name"
                    :label="`Kinnituseks kirjuta „${team.name}“`"
                    placeholder="Tiimi nimi"
                    autocomplete="off"
                    data-test="delete-team-name"
                    :error="errors.name"
                />

                <div class="flex items-center justify-end gap-3">
                    <R10Button variant="outline" @click="change(false)">
                        Loobu
                    </R10Button>

                    <R10Button
                        type="submit"
                        variant="danger"
                        data-test="delete-team-confirm"
                        :disabled="!canDelete || processing"
                    >
                        Kustuta tiim
                    </R10Button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
