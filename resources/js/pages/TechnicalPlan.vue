<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, provide, reactive, ref, watch } from 'vue';
import Diamond from '@/components/technical-plan/Diamond.vue';
import GateScreen from '@/components/technical-plan/GateScreen.vue';
import { hydratePlan } from '@/components/technical-plan/plan';
import { planKey } from '@/components/technical-plan/planKey';
import R10Button from '@/components/technical-plan/R10Button.vue';
import Stepper from '@/components/technical-plan/Stepper.vue';
import EquipmentStep from '@/components/technical-plan/steps/EquipmentStep.vue';
import NotesStep from '@/components/technical-plan/steps/NotesStep.vue';
import ReviewStep from '@/components/technical-plan/steps/ReviewStep.vue';
import ScenesStep from '@/components/technical-plan/steps/ScenesStep.vue';
import ShowStep from '@/components/technical-plan/steps/ShowStep.vue';
import SoundStep from '@/components/technical-plan/steps/SoundStep.vue';
import StandardInfoStep from '@/components/technical-plan/steps/StandardInfoStep.vue';
import type { LookupResult, Plan, WizardConfig } from '@/types/technicalPlan';

const props = defineProps<{
    config: WizardConfig;
    initialPlan: Plan | null;
}>();

const STORAGE_KEY = 'r10-techplan-v1';

const plan = reactive<Plan>(hydratePlan(props.initialPlan));
provide(planKey, plan);

const phase = ref<'gate' | 'wizard'>(props.initialPlan ? 'wizard' : 'gate');
const step = ref(0);

// Gate state
const lookupError = ref('');
const lookupResults = ref<LookupResult[]>([]);
const lookupBusy = ref(false);

// Save state
const submitting = ref(false);
const justSubmitted = ref(false);
const publicLink = ref('');
const linkCopied = ref(false);

// AI state
const aiLoading = ref(false);
const aiResult = ref('');
const aiError = ref('');

const stepComponents = [
    ShowStep,
    StandardInfoStep,
    SoundStep,
    ScenesStep,
    EquipmentStep,
    NotesStep,
];

const nextLabel = computed(() => (step.value === 5 ? 'Vaata üle' : 'Edasi'));

const missingRequired = computed(() => !plan.meta.contactEmail.trim());

/* ---- CSRF-aware JSON helpers ---------------------------------------- */

function csrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function requestJson(
    url: string,
    method: 'GET' | 'POST',
    body?: unknown,
): Promise<{ ok: boolean; status: number; data: Record<string, unknown> }> {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    let data: Record<string, unknown> = {};

    try {
        data = await response.json();
    } catch {
        data = {};
    }

    return { ok: response.ok, status: response.status, data };
}

/* ---- Plan lifecycle -------------------------------------------------- */

function scrollTop(): void {
    window.scrollTo({ top: 0 });
}

function resetTransient(): void {
    justSubmitted.value = false;
    publicLink.value = '';
    linkCopied.value = false;
    aiResult.value = '';
    aiError.value = '';
    submitting.value = false;
}

function loadIntoWizard(payload: Partial<Plan> | null, asNew = false): void {
    Object.assign(plan, hydratePlan(payload));

    if (asNew) {
        plan.token = null;
    }

    resetTransient();
    phase.value = 'wizard';
    step.value = 0;
    scrollTop();
}

function startBlank(): void {
    loadIntoWizard(null);
}

function reset(): void {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        /* ignore */
    }

    lookupError.value = '';
    lookupResults.value = [];
    Object.assign(plan, hydratePlan(null));
    resetTransient();
    phase.value = 'gate';
    step.value = 0;
    scrollTop();
}

function goTo(index: number): void {
    step.value = index;
    scrollTop();
}

function goNext(): void {
    if (step.value < 6) {
        goTo(step.value + 1);
    }
}

function goBack(): void {
    if (step.value > 0) {
        goTo(step.value - 1);
    }
}

/* ---- Gate actions ---------------------------------------------------- */

async function runLookup(email: string): Promise<void> {
    const trimmed = email.trim();

    if (!trimmed) {
        lookupError.value = 'Sisesta e-post.';
        lookupResults.value = [];

        return;
    }

    lookupBusy.value = true;
    lookupError.value = '';
    const { ok, data } = await requestJson('/tehnikaplaan/lookup', 'POST', {
        email: trimmed,
    });
    lookupBusy.value = false;

    if (!ok) {
        lookupResults.value = [];
        lookupError.value = 'Otsing ebaõnnestus. Kontrolli e-posti aadressi.';

        return;
    }

    const results = (data.results as LookupResult[]) ?? [];
    lookupResults.value = results;
    lookupError.value = results.length
        ? ''
        : 'Selle e-postiga pole veel ühtki plaani esitatud.';
}

async function openSubmission(token: string): Promise<void> {
    const { ok, data } = await requestJson(
        `/tehnikaplaan/plans/${encodeURIComponent(token)}`,
        'GET',
    );

    if (ok) {
        loadIntoWizard(data as Partial<Plan>, true);
    }
}

/* ---- Save / submit --------------------------------------------------- */

function buildPayload(submit: boolean): Record<string, unknown> {
    return {
        token: plan.token,
        submit,
        meta: { ...plan.meta },
        sound: { ...plan.sound },
        scenes: plan.scenes.map((s) => ({
            id: s.id,
            name: s.name,
            light: s.light,
            soundUrl: s.soundUrl,
            sound: s.sound,
            notes: s.notes,
        })),
        equipment: {
            items: plan.equipment.items.map((i) => ({ ...i })),
            smoke: plan.equipment.smoke,
            suggestions: plan.equipment.suggestions,
            suggestNote: plan.equipment.suggestNote,
        },
        extra: {
            notes: plan.extra.notes,
            files: plan.extra.files.map((f) => ({ ...f })),
        },
    };
}

async function savePlan(submit: boolean): Promise<boolean> {
    const { ok, data } = await requestJson(
        '/tehnikaplaan',
        'POST',
        buildPayload(submit),
    );

    if (!ok) {
        return false;
    }

    plan.token = (data.token as string) ?? plan.token;
    plan.status = (data.status as string) ?? plan.status;

    if (data.publicUrl) {
        publicLink.value = data.publicUrl as string;
    }

    return true;
}

async function submitPlan(): Promise<void> {
    if (missingRequired.value) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        return;
    }

    if (submitting.value) {
        return;
    }

    submitting.value = true;
    const ok = await savePlan(true);
    submitting.value = false;

    if (ok) {
        justSubmitted.value = true;
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: 'smooth',
        });
    }
}

async function createPublicLink(): Promise<void> {
    if (missingRequired.value) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        return;
    }

    if (submitting.value) {
        return;
    }

    submitting.value = true;
    linkCopied.value = false;
    const ok = await savePlan(false);
    submitting.value = false;

    if (ok && publicLink.value) {
        copyLink();
    }
}

function flashCopied(): void {
    linkCopied.value = true;
    window.setTimeout(() => (linkCopied.value = false), 2000);
}

function copyLink(): void {
    if (!publicLink.value) {
        return;
    }

    try {
        navigator.clipboard
            ?.writeText(publicLink.value)
            .then(flashCopied, flashCopied);
    } catch {
        flashCopied();
    }
}

function download(): void {
    window.print();
}

async function aiReview(): Promise<void> {
    if (aiLoading.value) {
        return;
    }

    if (missingRequired.value) {
        aiError.value = 'Täida enne kontakt-e-post.';

        return;
    }

    aiLoading.value = true;
    aiError.value = '';
    aiResult.value = '';
    const { ok, data } = await requestJson(
        '/tehnikaplaan/ai-review',
        'POST',
        buildPayload(false),
    );
    aiLoading.value = false;

    if (ok) {
        aiResult.value = (data.review as string) ?? '';
    } else {
        aiError.value =
            (data.message as string) ??
            'AI ülevaatus ebaõnnestus. Proovi hetke pärast uuesti.';
    }
}

/* ---- Local draft persistence ---------------------------------------- */

onMounted(() => {
    if (props.initialPlan) {
        return;
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return;
        }

        const saved = JSON.parse(raw) as {
            phase?: string;
            step?: number;
            plan?: Partial<Plan>;
        };

        if (saved.plan) {
            Object.assign(plan, hydratePlan(saved.plan));
        }

        if (saved.phase === 'wizard') {
            phase.value = 'wizard';
            step.value = Math.min(Math.max(saved.step ?? 0, 0), 6);
        }
    } catch {
        /* ignore malformed drafts */
    }
});

watch(
    [() => JSON.parse(JSON.stringify(plan)), phase, step],
    ([planSnapshot, currentPhase, currentStep]) => {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({
                    phase: currentPhase,
                    step: currentStep,
                    plan: planSnapshot,
                }),
            );
        } catch {
            /* ignore quota errors */
        }
    },
    { deep: true },
);
</script>

<template>
    <Head title="Etenduse tehnikaplaan" />

    <div class="min-h-screen bg-r10-paper font-r10-body text-r10-grey-700">
        <header
            class="r10-no-print sticky top-0 z-20 border-b border-white/15 bg-r10-navy"
        >
            <div
                class="mx-auto flex max-w-[1160px] items-center gap-5 px-6 py-3.5"
            >
                <span
                    class="font-r10-display text-xl font-black tracking-[0.06em] text-white"
                >
                    RUUTU<span class="text-r10-orange">10</span>
                </span>
                <div class="h-6 w-px bg-white/15" />
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span
                        class="font-r10-display text-[15px] leading-none font-semibold tracking-[0.03em] text-white uppercase"
                    >
                        Etenduse tehnikaplaan
                    </span>
                </div>
                <div
                    class="ml-auto hidden items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-[7px] sm:flex"
                >
                    <Diamond :size="8" />
                    <span
                        class="text-xs font-bold tracking-[0.04em] text-white"
                    >
                        Saada hiljemalt {{ config.deadlineHours }}h enne
                        etendust
                    </span>
                </div>
            </div>
        </header>

        <div
            class="mx-auto flex max-w-[1160px] flex-wrap items-start gap-[30px] px-6 pt-9 pb-16"
        >
            <Stepper
                v-if="phase === 'wizard'"
                :step="step"
                :optional-steps="[2, 5, 6]"
                @go="goTo"
                @reset="reset"
            />

            <main
                class="min-w-0 flex-1 basis-[520px] rounded-[22px] border border-r10-grey-200 bg-white p-6 shadow-[0_6px_18px_rgba(10,14,23,0.1)] sm:p-10"
            >
                <GateScreen
                    v-if="phase === 'gate'"
                    :lookup-error="lookupError"
                    :lookup-results="lookupResults"
                    :lookup-busy="lookupBusy"
                    @start-blank="startBlank"
                    @run-lookup="runLookup"
                    @open-submission="openSubmission"
                />

                <template v-else>
                    <component :is="stepComponents[step]" v-if="step < 6" />

                    <ReviewStep
                        v-else
                        :submitting="submitting"
                        :just-submitted="justSubmitted"
                        :public-link="publicLink"
                        :link-copied="linkCopied"
                        :ai-loading="aiLoading"
                        :ai-result="aiResult"
                        :ai-error="aiError"
                        @submit="submitPlan"
                        @download="download"
                        @create-link="createPublicLink"
                        @copy-link="copyLink"
                        @ai-review="aiReview"
                    />

                    <div
                        class="r10-no-print mt-9 flex items-center gap-4 border-t border-r10-grey-200 pt-6"
                    >
                        <R10Button
                            v-if="step > 0"
                            variant="outline"
                            size="md"
                            @click="goBack"
                        >
                            Tagasi
                        </R10Button>
                        <span
                            class="ml-auto text-xs font-bold tracking-[0.1em] text-r10-grey-500 uppercase"
                        >
                            Samm {{ step + 1 }} / 7
                        </span>
                        <R10Button
                            v-if="step < 6"
                            variant="primary"
                            size="md"
                            @click="goNext"
                        >
                            {{ nextLabel }}
                        </R10Button>
                    </div>
                </template>
            </main>
        </div>
    </div>
</template>
