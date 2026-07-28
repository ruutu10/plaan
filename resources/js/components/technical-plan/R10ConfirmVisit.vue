<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import R10Button from './R10Button.vue';
import R10Dialog from './R10Dialog.vue';

/**
 * "Are you sure?" for something carried out by an Inertia visit — leaving a
 * team, removing a member, withdrawing an invitation. The sibling
 * {@see R10ConfirmDelete} does the same for the JSON API; these post to the
 * web routes instead, which answer with a redirect and their own flash
 * message, so there is no toast to raise here.
 */
const props = defineProps<{
    title: string;
    confirmLabel: string;
    cancelLabel?: string;
    /** Null while the dialog has no subject yet. */
    action: UrlMethodPair | null;
    testId?: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const processing = ref(false);

function confirm(): void {
    if (!props.action) {
        return;
    }

    router.visit(props.action, {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <R10Dialog v-model:open="open" :title="title">
        <template #description>
            <slot name="description" />
        </template>

        <template #actions>
            <R10Button variant="outline" @click="open = false">
                {{ cancelLabel ?? 'Loobu' }}
            </R10Button>

            <R10Button
                variant="danger"
                :disabled="processing"
                :data-test="testId"
                @click="confirm"
            >
                {{ confirmLabel }}
            </R10Button>
        </template>
    </R10Dialog>
</template>
