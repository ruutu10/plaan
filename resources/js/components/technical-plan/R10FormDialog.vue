<script setup lang="ts">
import { watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import R10Button from './R10Button.vue';

/**
 * A modal holding a form: the R10 dialog chrome, the fields, and a
 * cancel/submit pair. The whole dialog is the form, so submitting from the
 * keyboard works as well as pressing the button.
 *
 * `opened` fires when the dialog is opened, which is where the caller resets
 * the form — a modal is mounted with the page and reused, so it would
 * otherwise still hold the last attempt.
 */
defineProps<{
    title: string;
    description?: string;
    submitLabel: string;
    processing?: boolean;
    testIdPrefix?: string;
}>();

const emit = defineEmits<{ submit: []; opened: [] }>();

const open = defineModel<boolean>('open', { required: true });

/**
 * Watched rather than hung off the dialog's own event: the pages open these by
 * setting the model, which the dialog never hears about, and a form that did
 * not reset would still be showing the last row's values.
 */
watch(open, (isOpen) => {
    if (isOpen) {
        emit('opened');
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="open = $event">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <form class="flex flex-col gap-6" @submit.prevent="emit('submit')">
                <DialogHeader>
                    <DialogTitle
                        class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                    >
                        {{ title }}
                    </DialogTitle>
                    <DialogDescription
                        v-if="description"
                        class="text-[15px] text-r10-grey-500"
                    >
                        {{ description }}
                    </DialogDescription>
                </DialogHeader>

                <slot />

                <div class="flex items-center justify-end gap-3">
                    <R10Button
                        variant="outline"
                        :disabled="processing"
                        :data-test="
                            testIdPrefix ? `${testIdPrefix}-cancel` : undefined
                        "
                        @click="open = false"
                    >
                        Loobu
                    </R10Button>

                    <R10Button
                        type="submit"
                        :disabled="processing"
                        :data-test="
                            testIdPrefix ? `${testIdPrefix}-submit` : undefined
                        "
                    >
                        {{ submitLabel }}
                    </R10Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
