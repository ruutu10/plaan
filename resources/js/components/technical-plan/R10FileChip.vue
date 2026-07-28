<script setup lang="ts">
import type { PlanFile } from '@/types/technicalPlan';
import Diamond from './Diamond.vue';
import { formatFileSize } from './plan';

/**
 * One attached file, in whichever state its upload is in: on its way up, up and
 * openable, or refused. Used for a plan's own attachments and for a scene's
 * sound cue alike.
 */
withDefaults(
    defineProps<{
        file: PlanFile;
        /** The wording for the open link; a scene cue spells out where it opens. */
        openLabel?: string;
    }>(),
    { openLabel: 'Ava' },
);

defineEmits<{ remove: [] }>();

const linkClass =
    'font-medium text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange';
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-[10px] border bg-white px-3.5 py-2.5"
        :class="
            file.status === 'error' ? 'border-r10-error' : 'border-r10-grey-200'
        "
    >
        <span
            v-if="file.status === 'uploading'"
            class="h-2 w-2 shrink-0 rotate-45 animate-[r10spin_1s_linear_infinite] rounded-[1px] bg-r10-orange"
        />
        <Diamond v-else :size="8" />

        <span class="flex min-w-0 flex-1 flex-col">
            <span
                class="overflow-hidden text-sm font-medium text-ellipsis whitespace-nowrap text-r10-ink"
            >
                {{ file.name }}
            </span>
            <span
                v-if="file.status === 'ready' && file.url"
                class="flex items-center gap-3 text-xs"
            >
                <a
                    :href="file.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    :class="linkClass"
                >
                    {{ openLabel }}
                </a>
                <a
                    v-if="file.downloadUrl"
                    :href="file.downloadUrl"
                    :class="linkClass"
                >
                    Laadi alla
                </a>
            </span>
            <span
                v-else-if="file.status === 'error'"
                class="text-xs text-r10-error"
            >
                {{ file.error }}
            </span>
            <span
                v-else-if="file.status === 'uploading'"
                class="text-xs text-r10-grey-500"
            >
                Laen üles…
            </span>
        </span>

        <span
            v-if="file.status !== 'error'"
            class="shrink-0 text-xs text-r10-grey-500"
        >
            {{ formatFileSize(file.size) }}
        </span>

        <button
            type="button"
            title="Eemalda"
            class="shrink-0 cursor-pointer border-none bg-transparent text-[15px] leading-none text-r10-grey-500 transition hover:text-r10-error"
            @click="$emit('remove')"
        >
            ✕
        </button>
    </div>
</template>
