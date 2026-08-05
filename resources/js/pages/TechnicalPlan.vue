<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, provide, reactive, ref, watch } from 'vue';
import {
    clearDraft,
    outgrowsLocalDraft,
    readDraft,
    writeDraft,
} from '@/components/technical-plan/draftStorage';
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
import type {
    Plan,
    PlanFile,
    PlanMeta,
    Scene,
    WizardConfig,
} from '@/types/technicalPlan';

const props = withDefaults(
    defineProps<{
        config: WizardConfig;
        initialPlan: Plan | null;
        /**
         * Whether this visitor may change the plan in front of them. False for
         * a guest — a share link opens the plan to read, and the login step is
         * what unlocks the rest of the wizard.
         */
        canEdit: boolean;
        /**
         * The night a reminder's link named, already resolved server-side. Set
         * only when the wizard was opened from one of those links, which is
         * also what lets it open past the first step — the step that would
         * otherwise be where this gets chosen.
         */
        initialPerformance?: PlanMeta | null;
        /** The step such a link asks for, counting from zero. */
        initialStep?: number;
    }>(),
    {
        initialPerformance: null,
        initialStep: 0,
    },
);

const plan = reactive<Plan>(hydratePlan(props.initialPlan));

// A reminder's link has already chosen the night, so the wizard starts with it
// filled in rather than making a performer who was just told which performance
// this is about pick it out of a list.
if (props.initialPerformance) {
    Object.assign(plan.meta, props.initialPerformance);
}

provide(planKey, plan);
provide(configKey, props.config);

const step = ref(props.initialStep);

/**
 * A plan opened by its share link, by somebody who may not change it: the
 * review page is the whole of it, as a document.
 */
const viewingOnly = computed(
    () => !props.canEdit && props.initialPlan !== null,
);

/** Whether the login step (0) is what the main panel is showing. */
const showLogin = ref(!props.canEdit && props.initialPlan === null);

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

/**
 * Every plan names the night it is for — the performer whose evening is not on
 * the books picks the stand-in performance offered on the first step — so the
 * picker is the one step that has to be answered before the rest opens. The
 * server refuses a plan without it either way; this is what keeps somebody from
 * filling in seven steps before being told.
 */
const performanceChosen = computed(() => plan.meta.performanceId !== null);

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
    clearDraft();
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
    // Every step of a plan somebody is only reading is behind the login: the
    // stepper stays there to say so, and asking for one takes them to it.
    if (viewingOnly.value) {
        openLogin();

        return;
    }

    // Nothing past the picker until the night has been chosen — see
    // `performanceChosen`. The stepper offers every step, so it is refused
    // here rather than only on the "Edasi" button.
    step.value = index > 0 && !performanceChosen.value ? 0 : index;
    scrollTop();
}

function openLogin(): void {
    showLogin.value = true;
    scrollTop();
}

function closeLogin(): void {
    showLogin.value = false;
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
        // Somebody logging in from a shared plan is logging in to work on that
        // plan — the mailed link brings them back to it rather than to a blank
        // wizard.
        token: props.initialPlan ? plan.token : null,
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

    const saved = readDraft();

    if (!saved?.plan) {
        return;
    }

    // A link that names a night overrides the stored draft — unless the draft
    // is for that very night, in which case the performer is coming back to
    // work they have already started and it would be theirs to lose.
    if (props.initialPerformance) {
        if (
            saved.plan.meta?.performanceId ===
            props.initialPerformance.performanceId
        ) {
            Object.assign(plan, hydratePlan(saved.plan));
        }

        return;
    }

    Object.assign(plan, hydratePlan(saved.plan));

    if (typeof saved.step === 'number') {
        step.value = Math.min(Math.max(saved.step, 0), 6);
    }
});

watch(
    [plan, step],
    () => {
        if (outgrowsLocalDraft(plan)) {
            clearDraft();

            return;
        }

        // A plan opened by its share link is never this browser's draft —
        // whether it is being read or edited, it is saved at its own link, and
        // storing it here would prefill the next new plan with it. The draft
        // this browser has half-written of its own must survive the visit.
        writeDraft({ step: step.value, plan }, props.initialPlan);
    },
    { deep: true },
);
</script>

<template>
    <Head title="Etenduse tehnikaplaan" />

    <R10Layout title="Etenduse tehnikaplaan" :no-print-header="true">
        <div
            class="mx-auto flex max-w-[1160px] flex-wrap items-start gap-6 px-4 pt-6 pb-12 sm:px-6 sm:pt-9 sm:pb-16 lg:gap-[30px]"
        >
            <Stepper
                :step="step"
                :optional-steps="[2, 5, 6]"
                :login-active="showLogin"
                :wizard-clickable="canEdit || viewingOnly"
                :login-clickable="!canEdit"
                :show-reset="canEdit"
                @go="goTo"
                @login="openLogin"
                @reset="reset"
            />

            <main
                class="min-w-0 flex-1 basis-[520px] rounded-[22px] border border-r10-grey-200 bg-white p-4 shadow-[0_6px_18px_rgba(10,14,23,0.1)] sm:p-6 lg:p-10"
            >
                <LoginScreen
                    v-if="showLogin"
                    :busy="loginBusy"
                    :sent="loginSent"
                    :error="loginError"
                    :sent-to="loginSentTo"
                    :viewing-plan="viewingOnly"
                    @send-link="sendLoginLink"
                    @back="closeLogin"
                />

                <template v-else>
                    <component
                        :is="stepComponents[step]"
                        v-if="canEdit && step < 6"
                    />

                    <ReviewStep
                        v-else
                        :read-only="!canEdit"
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

                    <!-- Both buttons and the counter rarely fit one phone-width
                         line, so the counter takes its own line above them. -->
                    <div
                        v-if="canEdit"
                        class="r10-no-print mt-9 flex flex-wrap items-center gap-x-4 gap-y-3 border-t border-r10-grey-200 pt-6"
                    >
                        <R10Button
                            v-if="step > 0"
                            variant="outline"
                            size="md"
                            class="order-2 sm:order-none"
                            @click="goBack"
                        >
                            Tagasi
                        </R10Button>
                        <span
                            class="order-1 w-full text-center text-xs font-bold tracking-[0.1em] text-r10-grey-500 uppercase sm:order-none sm:ml-auto sm:w-auto sm:text-left"
                        >
                            <template v-if="!performanceChosen">
                                Vali etendus, et edasi liikuda
                            </template>
                            <template v-else>Samm {{ step + 1 }} / 7</template>
                        </span>
                        <R10Button
                            v-if="step < 6"
                            variant="primary"
                            size="md"
                            :disabled="!performanceChosen"
                            class="order-3 ml-auto sm:order-none sm:ml-0"
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
