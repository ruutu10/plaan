<script setup lang="ts">
import { Sparkles, UserPen } from '@lucide/vue';
import { computed } from 'vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import { formatEstonianTimestamp } from '@/lib/date';
import type { CreatedBy } from '@/types';

/**
 * Where a show or a performance came from and when — the two facts about a
 * record that nobody sets and everybody eventually asks about. Read-only by
 * nature: the server decides both, and there is no field to correct because
 * there is nothing here anyone should be able to rewrite.
 *
 * Shared by the show page and the performance dialog so the two never say the
 * same thing in two different ways.
 */
const props = defineProps<{
    createdBy: CreatedBy;
    /** ISO 8601, already on the venue's clock. */
    createdAt: string | null;
}>();

const wasImported = computed(() => props.createdBy === 'planka-import');

const originLabel = computed(() =>
    wasImported.value ? 'Planka import' : 'Käsitsi lisatud',
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span
            class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
        >
            Kirje päritolu
        </span>
        <span class="-mt-0.5 text-xs text-r10-grey-500">
            Kes kirje lõi. Imporditud kirje andmed on loetud Planka kaardilt,
            mitte käsitsi sisestatud.
        </span>

        <div
            class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border-2 border-r10-grey-200 bg-r10-grey-100 px-4 py-3"
        >
            <R10Pill
                :tone="wasImported ? 'navy' : 'neutral'"
                size="md"
                data-test="record-created-by"
            >
                <component
                    :is="wasImported ? Sparkles : UserPen"
                    class="h-3.5 w-3.5"
                />
                {{ originLabel }}
            </R10Pill>

            <span class="text-[13px] text-r10-grey-700">
                Loodud
                <span
                    class="font-medium text-r10-ink tabular-nums"
                    data-test="record-created-at"
                >
                    {{ formatEstonianTimestamp(createdAt) }}
                </span>
            </span>
        </div>
    </div>
</template>
