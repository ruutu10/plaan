<script setup lang="ts">
import type { PriorPlan } from '@/types/technicalPlan';

/**
 * Where a new plan starts from, once its night has been chosen: a blank slate,
 * or a copy of a plan already handed in for the same format. Offered under the
 * selected performance in the first step, the stand-in performance included —
 * a group that has filed a plan without a night before is exactly the one
 * likely to be doing it again.
 */
defineProps<{
    plans: PriorPlan[];
    /** The plan being copied, or null for a blank slate. */
    selected: string | null;
    /** A copy is being fetched; the choice must not be changed underneath it. */
    busy: boolean;
}>();

const emit = defineEmits<{ choose: [token: string | null] }>();
</script>

<template>
    <div class="border-t border-r10-orange/30 px-4 pt-3 pb-3.5">
        <span
            class="mb-2 block font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-grey-700 uppercase"
        >
            Millest alustada?
        </span>
        <div class="flex max-h-72 flex-col gap-1 overflow-y-auto">
            <button
                type="button"
                :disabled="busy"
                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-white/70"
                @click="emit('choose', null)"
            >
                <span
                    :class="[
                        'flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2',
                        selected === null
                            ? 'border-r10-orange'
                            : 'border-r10-grey-200',
                    ]"
                >
                    <span
                        v-if="selected === null"
                        class="h-2 w-2 rounded-full bg-r10-orange"
                    />
                </span>
                <span class="text-sm font-semibold text-r10-ink">
                    Alusta tühjalt lehelt
                </span>
            </button>

            <button
                v-for="prior in plans"
                :key="prior.token"
                type="button"
                :disabled="busy"
                class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-white/70"
                @click="emit('choose', prior.token)"
            >
                <span
                    :class="[
                        'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2',
                        selected === prior.token
                            ? 'border-r10-orange'
                            : 'border-r10-grey-200',
                    ]"
                >
                    <span
                        v-if="selected === prior.token"
                        class="h-2 w-2 rounded-full bg-r10-orange"
                    />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm text-r10-ink">
                        Kopeeri varem esitatud plaan
                        <span class="font-semibold">· {{ prior.label }}</span>
                    </span>
                    <span
                        v-if="prior.author"
                        class="block text-[12px] text-r10-grey-500"
                    >
                        Koostas {{ prior.author }}
                    </span>
                </span>
            </button>
        </div>
    </div>
</template>
