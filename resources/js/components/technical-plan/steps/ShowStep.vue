<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { formatEstonianDateTime } from '@/lib/date';
import { requestJson } from '@/lib/http';
import type { UpcomingPerformance } from '@/types/technicalPlan';
import { applyPlanContent, resetPlanContent } from '../plan';
import { usePlan } from '../planKey';
import PriorPlanPicker from '../PriorPlanPicker.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();

const performances = ref<UpcomingPerformance[]>([]);
/**
 * The stand-in performance, offered below the list: a plan always names the
 * night it is for, so a performer whose evening is not on the books files it
 * here and the crew move it once the performance has been registered.
 */
const placeholder = ref<UpcomingPerformance | null>(null);
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
        placeholder.value =
            (data.placeholder as UpcomingPerformance | undefined) ?? null;
    } else {
        loadError.value = 'Etenduste laadimine ebaõnnestus.';
        performances.value = [];
        placeholder.value = null;
    }

    loading.value = false;
}

function isSelected(performance: UpcomingPerformance): boolean {
    return plan.meta.performanceId === performance.id;
}

/**
 * Reset to a fresh blank plan whenever the chosen performance changes — unless
 * the plan has already been saved. Moving one of those onto another night is
 * re-filing it, not starting over: it is what the crew does with the plans
 * handed in under the stand-in performance once the real one is on the books,
 * and the content is the whole of what they are keeping.
 */
function freshStart(): void {
    sourceToken.value = null;

    if (plan.token !== null) {
        return;
    }

    resetPlanContent(plan);
}

function selectPerformance(performance: UpcomingPerformance): void {
    if (isSelected(performance)) {
        return;
    }

    plan.meta.performanceId = performance.id;
    plan.meta.performer = performance.performer;
    plan.meta.formatName = performance.formatName;
    plan.meta.performanceDate = performance.performanceDate;
    plan.meta.duration = performance.duration;
    plan.meta.description = performance.description;
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
                            {{ performance.formatName }}
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
                                    performance.performanceDate,
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

                <PriorPlanPicker
                    v-if="
                        isSelected(performance) && performance.priorPlans.length
                    "
                    :plans="performance.priorPlans"
                    :selected="sourceToken"
                    :busy="applyingSource"
                    @choose="chooseSource"
                />
            </div>

            <div
                v-if="placeholder"
                :class="[
                    'rounded-xl border-2 border-dashed transition-colors',
                    isSelected(placeholder)
                        ? 'border-r10-orange bg-r10-orange-100'
                        : 'border-r10-grey-200 bg-white',
                ]"
            >
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-start gap-3.5 px-4 py-3.5 text-left"
                    @click="selectPerformance(placeholder)"
                >
                    <span
                        :class="[
                            'mt-1 h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px]',
                            isSelected(placeholder)
                                ? 'bg-r10-orange'
                                : 'bg-r10-grey-200',
                        ]"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            class="block font-r10-body text-sm font-semibold text-r10-ink"
                        >
                            Etendust pole nimekirjas
                        </span>
                        <span
                            class="mt-0.5 block text-[13px] text-r10-grey-500"
                        >
                            Plaan jõuab tehnikuni ka nii. Kirjuta etenduse nimi,
                            kuupäev ja kellaaeg viimase sammu lisainfo lahtrisse
                            — tehnik seob plaani hiljem õige etendusega.
                        </span>
                    </span>
                </button>

                <PriorPlanPicker
                    v-if="
                        isSelected(placeholder) && placeholder.priorPlans.length
                    "
                    :plans="placeholder.priorPlans"
                    :selected="sourceToken"
                    :busy="applyingSource"
                    @choose="chooseSource"
                />
            </div>
        </div>
    </section>
</template>
