<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { formatEstonianDateTime } from '@/lib/date';
import { requestJson } from '@/lib/http';
import type { UpcomingPerformance } from '@/types/technicalPlan';
import { applyPlanContent, resetPlanContent } from '../plan';
import { usePlan } from '../planKey';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();

const performances = ref<UpcomingPerformance[]>([]);
const loading = ref(true);
const loadError = ref('');

// null = start from a blank plan; otherwise the token of a prior plan to copy.
const sourceToken = ref<string | null>(null);
const applyingSource = ref(false);

async function loadPerformances(): Promise<void> {
    loading.value = true;
    loadError.value = '';

    const { ok, data } = await requestJson('/api/tehnikaplaan/performances');

    if (ok) {
        performances.value =
            (data.results as UpcomingPerformance[] | undefined) ?? [];
    } else {
        loadError.value = 'Etenduste laadimine ebaõnnestus.';
        performances.value = [];
    }

    loading.value = false;
}

function isSelected(performance: UpcomingPerformance): boolean {
    return plan.meta.performanceId === performance.id;
}

/** Reset to a fresh blank plan whenever the chosen performance changes. */
function freshStart(): void {
    sourceToken.value = null;
    plan.token = null;
    resetPlanContent(plan);
}

function selectPerformance(performance: UpcomingPerformance): void {
    if (isSelected(performance)) {
        return;
    }

    plan.meta.performanceId = performance.id;
    plan.meta.performer = performance.performer;
    plan.meta.showName = performance.showName;
    plan.meta.showDate = performance.showDate;
    plan.meta.duration = performance.duration;
    plan.meta.description = performance.description;
    freshStart();
}

function selectNotListed(): void {
    if (plan.meta.performanceId === null) {
        return;
    }

    plan.meta.performanceId = null;
    plan.meta.performer = '';
    plan.meta.showName = '';
    plan.meta.showDate = '';
    plan.meta.duration = null;
    plan.meta.description = '';
    freshStart();
}

/**
 * Pick where the new plan starts from: a blank slate (null) or a copy of a
 * previously submitted plan. The performance meta is always kept as-is.
 */
async function chooseSource(token: string | null): Promise<void> {
    if (applyingSource.value) {
        return;
    }

    sourceToken.value = token;
    plan.token = null;

    if (token === null) {
        resetPlanContent(plan);

        return;
    }

    applyingSource.value = true;

    try {
        const { ok, data } = await requestJson(
            `/api/tehnikaplaan/plans/${encodeURIComponent(token)}/copy`,
            'POST',
        );

        if (ok) {
            applyPlanContent(plan, data);
        }
    } finally {
        applyingSource.value = false;
    }
}

onMounted(loadPerformances);
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 1 / 7 · Etendus"
            title="Vali etendus"
            lead="Vali nimekirjast eelseisev etendus. Plaani täitmist saad alustada kas tühjalt lehelt või varasemalt saadetud plaani kopeerides."
        />

        <div v-if="loading" class="flex flex-col gap-3">
            <div
                v-for="n in 3"
                :key="n"
                class="h-[74px] animate-pulse rounded-xl border-2 border-r10-grey-200 bg-r10-grey-100"
            />
        </div>

        <p v-else-if="loadError" class="text-sm text-r10-orange">
            {{ loadError }}
        </p>

        <div v-else class="flex flex-col gap-3">
            <p
                v-if="!performances.length"
                class="text-[13px] text-r10-grey-500"
            >
                Ühtegi eelseisvat etendust pole nimekirjas.
            </p>

            <div
                v-for="performance in performances"
                :key="performance.id"
                :class="[
                    'rounded-xl border-2 transition-colors',
                    isSelected(performance)
                        ? 'border-r10-orange bg-r10-orange-100'
                        : 'border-r10-grey-200 bg-white',
                ]"
            >
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-start gap-3.5 px-4 py-3.5 text-left"
                    @click="selectPerformance(performance)"
                >
                    <span
                        :class="[
                            'mt-1 h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px]',
                            isSelected(performance)
                                ? 'bg-r10-orange'
                                : 'bg-r10-grey-200',
                        ]"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            class="block font-r10-display text-base font-semibold text-r10-ink"
                        >
                            {{ performance.showName }}
                            <!-- An evening several groups share would otherwise
                                 offer three identical rows to choose between. -->
                            <template v-if="performance.title">
                                — {{ performance.title }}
                            </template>
                        </span>
                        <span
                            class="mt-0.5 block text-[13px] text-r10-grey-500"
                        >
                            {{
                                formatEstonianDateTime(
                                    performance.showDate,
                                    performance.startTime,
                                )
                            }}
                            <template v-if="performance.performer">
                                · {{ performance.performer }}
                            </template>
                            <template v-if="performance.duration">
                                · {{ performance.duration }} min
                            </template>
                        </span>
                    </span>
                </button>

                <div
                    v-if="
                        isSelected(performance) && performance.priorPlans.length
                    "
                    class="border-t border-r10-orange/30 px-4 pt-3 pb-3.5"
                >
                    <span
                        class="mb-2 block font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-grey-700 uppercase"
                    >
                        Millest alustada?
                    </span>
                    <div class="flex max-h-72 flex-col gap-1 overflow-y-auto">
                        <button
                            type="button"
                            :disabled="applyingSource"
                            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-white/70"
                            @click="chooseSource(null)"
                        >
                            <span
                                :class="[
                                    'flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2',
                                    sourceToken === null
                                        ? 'border-r10-orange'
                                        : 'border-r10-grey-200',
                                ]"
                            >
                                <span
                                    v-if="sourceToken === null"
                                    class="h-2 w-2 rounded-full bg-r10-orange"
                                />
                            </span>
                            <span class="text-sm font-semibold text-r10-ink">
                                Alusta tühjalt lehelt
                            </span>
                        </button>

                        <button
                            v-for="prior in performance.priorPlans"
                            :key="prior.token"
                            type="button"
                            :disabled="applyingSource"
                            class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-white/70"
                            @click="chooseSource(prior.token)"
                        >
                            <span
                                :class="[
                                    'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2',
                                    sourceToken === prior.token
                                        ? 'border-r10-orange'
                                        : 'border-r10-grey-200',
                                ]"
                            >
                                <span
                                    v-if="sourceToken === prior.token"
                                    class="h-2 w-2 rounded-full bg-r10-orange"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-r10-ink">
                                    Kopeeri varem esitatud plaan
                                    <span class="font-semibold"
                                        >· {{ prior.label }}</span
                                    >
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
            </div>

            <div
                :class="[
                    'rounded-xl border-2 border-dashed transition-colors',
                    plan.meta.performanceId === null
                        ? 'border-r10-orange bg-r10-orange-100'
                        : 'border-r10-grey-200 bg-white',
                ]"
            >
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center gap-3.5 px-4 py-3.5 text-left"
                    @click="selectNotListed"
                >
                    <span
                        :class="[
                            'h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px]',
                            plan.meta.performanceId === null
                                ? 'bg-r10-orange'
                                : 'bg-r10-grey-200',
                        ]"
                    />
                    <span
                        class="font-r10-body text-sm font-semibold text-r10-ink"
                    >
                        Etendust pole nimekirjas
                    </span>
                </button>
            </div>
        </div>
    </section>
</template>
