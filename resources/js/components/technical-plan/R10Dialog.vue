<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * The dialog chrome every R10 modal wears: the paper background, the display
 * title and the muted description. Only the body and the buttons differ, so
 * those are slots.
 */
defineProps<{ title: string; description?: string }>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog :open="open" @update:open="open = $event">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <DialogHeader>
                <DialogTitle
                    class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                >
                    {{ title }}
                </DialogTitle>
                <DialogDescription
                    v-if="description || $slots.description"
                    class="text-[15px] text-r10-grey-500"
                >
                    <slot name="description">{{ description }}</slot>
                </DialogDescription>
            </DialogHeader>

            <slot />

            <div
                v-if="$slots.actions"
                class="flex items-center justify-end gap-3"
            >
                <slot name="actions" />
            </div>
        </DialogContent>
    </Dialog>
</template>
