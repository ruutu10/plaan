<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, provide, reactive, ref, watch } from 'vue';
import LoginScreen from '@/components/technical-plan/LoginScreen.vue';
import { hydratePlan } from '@/components/technical-plan/plan';
import { configKey, planKey } from '@/components/technical-plan/planKey';
import R10Button from '@/components/technical-plan/R10Button.vue';
import Stepper from '@/components/technical-plan/Stepper.vue';
import EquipmentStep from '@/components/technical-plan/steps/EquipmentStep.vue';
import NotesStep from '@/components/technical-plan/steps/NotesStep.vue';
import ReviewStep from '@/components/technical-plan/steps/ReviewStep.vue';
import ScenesStep from '@/components/technical-plan/steps/ScenesStep.vue';
import ShowStep from '@/components/technical-plan/steps/ShowStep.vue';
import SoundStep from '@/components/technical-plan/steps/SoundStep.vue';
import StandardInfoStep from '@/components/technical-plan/steps/StandardInfoStep.vue';
import R10Layout from '@/layouts/R10Layout.vue';
import { failureMessage, requestJson } from '@/lib/http';
import technicalPlan from '@/routes/technical-plan';
import type { User } from '@/types';
import type {
    Plan,
    PlanFile,
    Scene,
    WizardConfig,
} from '@/types/technicalPlan';

const props = defineProps<{
    config: WizardConfig;
    initialPlan: Plan | null;
}>();

const STORAGE_KEY = 'r10-techplan-v1';

const page = usePage<{ auth: { user: User | null } }>();
const user = computed(() => page.props.auth.user);

const plan = reactive<Plan>(hydratePlan(props.initialPlan));
provide(planKey, plan);
provide(configKey, props.config);

const step = ref(0);

// Login state
const loginBusy = ref(false);
const loginSent = ref(false);
const loginError = ref('');
const loginSentTo = ref('');

// Save state
const submitting = ref(false);
const justSubmitted = ref(false);
const publicLink = ref('');
const linkCopied = ref(false);
const saveError = ref('');

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

/* ---- Plan lifecycle -------------------------------------------------- */

function scrollTop(): void {
    window.scrollTo({ top: 0 });
}

function resetTransient(): void {
    justSubmitted.value = false;
    publicLink.value = '';
    linkCopied.value = false;
    saveError.value = '';
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
    step.value = 0;
    scrollTop();
}

function reset(): void {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        /* ignore */
    }

    loadIntoWizard(null);

    // A plan opened from a shared link lives at that link's own URL. Starting
    // over must leave it behind, or a reload would pull the shared plan back
    // in. The wizard state was just cleared, so keep it across the visit.
    const wizardUrl = technicalPlan.index.url();

    if (window.location.pathname !== wizardUrl) {
        router.visit(wizardUrl, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }
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

/* ---- Login ----------------------------------------------------------- */

async function sendLoginLink(email: string): Promise<void> {
    const trimmed = email.trim();

    if (!trimmed) {
        loginError.value = 'Sisesta e-post.';

        return;
    }

    if (loginBusy.value) {
        return;
    }

    loginBusy.value = true;
    loginError.value = '';
    const { ok, data } = await requestJson('/api/tehnikaplaan/login', 'POST', {
        email: trimmed,
    });
    loginBusy.value = false;

    if (ok) {
        loginSent.value = true;
        loginSentTo.value = trimmed;

        return;
    }

    loginError.value =
        (data.message as string) ??
        'Lingi saatmine ebaõnnestus. Proovi uuesti.';
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
            // Only a finished upload has a handle worth sending.
            soundFile:
                s.soundFile?.status === 'ready'
                    ? {
                          id: s.soundFile.id,
                          name: s.soundFile.name,
                          size: s.soundFile.size,
                      }
                    : null,
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
            files: plan.extra.files
                .filter((f) => f.status !== 'uploading' && f.status !== 'error')
                .map((f) => ({ id: f.id, name: f.name, size: f.size })),
        },
    };
}

async function savePlan(submit: boolean): Promise<boolean> {
    saveError.value = '';

    const { ok, status, data } = await requestJson(
        '/api/tehnikaplaan',
        'POST',
        buildPayload(submit),
    );

    if (!ok) {
        // A rejected key means the plan behind it is gone, so saving over it
        // can never succeed — starting over is the only way out, and the user
        // has to be told that rather than left pressing the button.
        const keyRejected = Boolean(
            (data.errors as Record<string, string[]> | undefined)?.token,
        );

        saveError.value = keyRejected
            ? 'Selle plaani võtit ei tunta enam ära — plaan on vahepeal kustutatud. Vajuta vasakul „Alusta otsast“, et saata sisu uue plaanina.'
            : failureMessage(
                  status,
                  data,
                  'Plaani salvestamine ebaõnnestus. Proovi uuesti.',
              );

        return false;
    }

    plan.token = (data.token as string) ?? plan.token;
    plan.status = (data.status as string) ?? plan.status;

    if (data.publicUrl) {
        publicLink.value = data.publicUrl as string;
    }

    // Adopt the canonical attachment list — the server may have re-keyed files
    // as it moved them onto the plan, so keep the wizard in sync. The scenes
    // come back for the same reason: each carries its sound file's handle.
    if (Array.isArray(data.files)) {
        plan.extra.files = (data.files as PlanFile[]).map((f) => ({
            ...f,
            status: 'ready' as const,
        }));
    }

    if (Array.isArray(data.scenes)) {
        (data.scenes as Scene[]).forEach((saved, index) => {
            if (plan.scenes[index]) {
                plan.scenes[index].soundFile = saved.soundFile
                    ? { ...saved.soundFile, status: 'ready' as const }
                    : null;
            }
        });
    }

    return true;
}

async function submitPlan(): Promise<void> {
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

    aiLoading.value = true;
    aiError.value = '';
    aiResult.value = '';
    const { ok, status, data } = await requestJson(
        '/api/tehnikaplaan/ai-review',
        'POST',
        buildPayload(false),
    );
    aiLoading.value = false;

    if (ok) {
        aiResult.value = (data.review as string) ?? '';
    } else {
        aiError.value = failureMessage(
            status,
            data,
            'AI ülevaatus ebaõnnestus. Viga logiti.',
        );
    }
}

/* ---- Local draft persistence ---------------------------------------- */

onMounted(() => {
    if (props.initialPlan) {
        return;
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (raw) {
            const saved = JSON.parse(raw) as {
                step?: number;
                plan?: Partial<Plan>;
            };

            if (saved.plan) {
                Object.assign(plan, hydratePlan(saved.plan));
            }

            if (typeof saved.step === 'number') {
                step.value = Math.min(Math.max(saved.step, 0), 6);
            }
        }
    } catch {
        /* ignore malformed drafts */
    }
});

watch(
    [plan, step],
    () => {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({
                    step: step.value,
                    plan,
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

    <R10Layout title="Etenduse tehnikaplaan" :no-print-header="true">
        <div
            class="mx-auto flex max-w-[1160px] flex-wrap items-start gap-[30px] px-6 pt-9 pb-16"
        >
            <Stepper
                :step="step"
                :optional-steps="[2, 5, 6]"
                :login-active="!user"
                :wizard-clickable="!!user"
                :show-reset="!!user"
                @go="goTo"
                @reset="reset"
            />

            <main
                class="min-w-0 flex-1 basis-[520px] rounded-[22px] border border-r10-grey-200 bg-white p-6 shadow-[0_6px_18px_rgba(10,14,23,0.1)] sm:p-10"
            >
                <LoginScreen
                    v-if="!user"
                    :busy="loginBusy"
                    :sent="loginSent"
                    :error="loginError"
                    :sent-to="loginSentTo"
                    @send-link="sendLoginLink"
                />

                <template v-else>
                    <component :is="stepComponents[step]" v-if="step < 6" />

                    <ReviewStep
                        v-else
                        :submitting="submitting"
                        :just-submitted="justSubmitted"
                        :save-error="saveError"
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
    </R10Layout>
</template>
