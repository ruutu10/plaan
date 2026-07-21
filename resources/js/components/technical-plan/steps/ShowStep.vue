<script setup lang="ts">
import { onMounted, ref } from 'vue';
import type { UpcomingPerformance } from '@/types/technicalPlan';
import { usePlan } from '../planKey';
import R10Input from '../R10Input.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();

const performances = ref<UpcomingPerformance[]>([]);
const loading = ref(true);
const loadError = ref('');

function formatDate(iso: string): string {
    const parts = iso.split('-');

    return parts.length === 3 ? `${parts[2]}.${parts[1]}.${parts[0]}` : iso;
}

async function loadPerformances(): Promise<void> {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await fetch('/api/tehnikaplaan/performances', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();
        performances.value =
            (data.results as UpcomingPerformance[] | undefined) ?? [];
    } catch {
        loadError.value = 'Etenduste laadimine ebaõnnestus.';
        performances.value = [];
    } finally {
        loading.value = false;
    }
}

function selectPerformance(performance: UpcomingPerformance): void {
    plan.meta.performanceId = performance.id;
    plan.meta.performer = performance.performer;
    plan.meta.showName = performance.showName;
    plan.meta.showDate = performance.showDate;
    plan.meta.duration = performance.duration;
    plan.meta.description = performance.description;
}

function selectNotListed(): void {
    plan.meta.performanceId = null;
    plan.meta.performer = '';
    plan.meta.showName = '';
    plan.meta.showDate = '';
    plan.meta.duration = null;
    plan.meta.description = '';
}

onMounted(loadPerformances);
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 1 / 7 · Etendus"
            title="Vali etendus"
            lead="Vali nimekirjast eelseisev etendus, mille kohta tehnikaplaani koostad."
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

            <button
                v-for="performance in performances"
                :key="performance.id"
                type="button"
                :class="[
                    'flex items-start gap-3.5 rounded-xl border-2 px-4 py-3.5 text-left transition-colors',
                    plan.meta.performanceId === performance.id
                        ? 'border-r10-orange bg-r10-orange-100'
                        : 'hover:border-r10-grey-300 border-r10-grey-200 bg-white',
                ]"
                @click="selectPerformance(performance)"
            >
                <span
                    :class="[
                        'mt-1 h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px]',
                        plan.meta.performanceId === performance.id
                            ? 'bg-r10-orange'
                            : 'bg-r10-grey-300',
                    ]"
                />
                <span class="min-w-0 flex-1">
                    <span
                        class="block font-r10-display text-base font-semibold text-r10-ink"
                    >
                        {{ performance.showName }}
                    </span>
                    <span class="mt-0.5 block text-[13px] text-r10-grey-500">
                        {{ formatDate(performance.showDate) }}
                        <template v-if="performance.performer">
                            · {{ performance.performer }}
                        </template>
                        <template v-if="performance.duration">
                            · {{ performance.duration }} min
                        </template>
                    </span>
                </span>
            </button>

            <button
                type="button"
                :class="[
                    'flex items-center gap-3.5 rounded-xl border-2 border-dashed px-4 py-3.5 text-left transition-colors',
                    plan.meta.performanceId === null
                        ? 'border-r10-orange bg-r10-orange-100'
                        : 'hover:border-r10-grey-300 border-r10-grey-200 bg-white',
                ]"
                @click="selectNotListed"
            >
                <span
                    :class="[
                        'h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px]',
                        plan.meta.performanceId === null
                            ? 'bg-r10-orange'
                            : 'bg-r10-grey-300',
                    ]"
                />
                <span class="font-r10-body text-sm font-semibold text-r10-ink">
                    Etendust pole nimekirjas
                </span>
            </button>
        </div>

        <div class="mt-[30px] border-t border-r10-grey-200 pt-6">
            <div
                class="mb-1 font-r10-display text-base font-semibold tracking-[0.03em] text-r10-ink uppercase"
            >
                Kontakt
            </div>
            <p
                class="mt-0 mb-[18px] text-[13px] text-r10-grey-500"
            >
                Saadame sulle tehnikaplaani koopia e-mailile.
                Samuti saad hiljem e-posti järgi avada varem saadetud tehnikaplaane.

            </p>
            <div class="max-w-md">
                <R10Input
                    v-model="plan.meta.contactEmail"
                    label="E-post"
                    type="email"
                    placeholder="ando@ruutu10.ee"
                    required
                />
            </div>
        </div>
    </section>
</template>
