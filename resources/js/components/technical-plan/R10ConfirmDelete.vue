<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { useHttp } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { toast } from 'vue-sonner';
import R10Button from './R10Button.vue';
import R10Dialog from './R10Dialog.vue';

/**
 * "Are you sure?" — the same dialog for deleting a show, a performance, a team
 * or a member. Each of those had its own copy of this file with three strings
 * swapped, which is how one of them came to be missing its warning.
 *
 * `action` is the Wayfinder route the confirmation posts to.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        /** Consequence worth stopping over, shown as a warning callout. */
        warning?: string;
        confirmLabel?: string;
        successToast: string;
        errorToast?: string;
        /**
         * The route the confirmation posts to. Null while the dialog has no
         * subject yet — it is mounted with the page, not with the row.
         */
        action: UrlMethodPair | null;
        testIdPrefix?: string;
    }>(),
    {
        confirmLabel: 'Kustuta',
        errorToast: 'Kustutamine ebaõnnestus. Proovi uuesti.',
    },
);

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

const http = useHttp();

async function remove(): Promise<void> {
    if (!props.action) {
        return;
    }

    try {
        await http.submit(props.action);

        emit('deleted');
        open.value = false;

        toast.success(props.successToast);
    } catch {
        toast.error(props.errorToast);
    }
}
</script>

<template>
    <R10Dialog v-model:open="open" :title="title" :description="description">
        <template v-if="$slots.description" #description>
            <slot name="description" />
        </template>

        <p
            v-if="warning || $slots.warning"
            :data-test="testIdPrefix ? `${testIdPrefix}-warning` : undefined"
            class="flex gap-3 rounded-lg border-2 border-r10-orange bg-r10-orange-100 p-4 text-[14px] text-r10-grey-700"
        >
            <TriangleAlert
                class="mt-0.5 h-5 w-5 shrink-0 text-r10-orange-700"
            />
            <span
                ><slot name="warning">{{ warning }}</slot></span
            >
        </p>

        <template #actions>
            <R10Button
                variant="outline"
                :disabled="http.processing"
                :data-test="testIdPrefix ? `${testIdPrefix}-cancel` : undefined"
                @click="open = false"
            >
                Loobu
            </R10Button>

            <R10Button
                variant="danger"
                :disabled="http.processing"
                :data-test="
                    testIdPrefix ? `${testIdPrefix}-confirm` : undefined
                "
                @click="remove"
            >
                {{ confirmLabel }}
            </R10Button>
        </template>
    </R10Dialog>
</template>
