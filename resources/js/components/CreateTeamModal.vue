<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store } from '@/routes/teams';

const open = defineModel<boolean>('open', { required: true });

/** Bumped to remount the form, which is how a closed dialog forgets what was typed. */
const formKey = ref(0);

watch(open, (value) => {
    if (!value) {
        formKey.value++;
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <Form
                :key="formKey"
                v-bind="store.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle
                        class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                    >
                        Uus tiim
                    </DialogTitle>
                    <DialogDescription class="text-[15px] text-r10-grey-500">
                        Loo tiim, millega koos formaate hallata. Tiimi omanik
                        oled sina.
                    </DialogDescription>
                </DialogHeader>

                <R10Input
                    name="name"
                    label="Tiimi nimi"
                    required
                    placeholder="Minu tiim"
                    data-test="create-team-name"
                    :error="errors.name"
                />

                <div class="flex items-center justify-end gap-3">
                    <R10Button variant="outline" @click="open = false">
                        Loobu
                    </R10Button>

                    <R10Button
                        type="submit"
                        data-test="create-team-submit"
                        :disabled="processing"
                    >
                        Loo tiim
                    </R10Button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
