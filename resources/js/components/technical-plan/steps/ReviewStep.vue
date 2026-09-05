<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Download,
    Link2,
    RefreshCw,
    Save,
    Send,
    Sparkles,
    Spotlight,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { User } from '@/types';
import Diamond from '../Diamond.vue';
import { canSaveDraft, isDelivered } from '../plan';
import PlanDocument from '../PlanDocument.vue';
import { usePlan } from '../planKey';
import { presentPlan } from '../presentPlan';
import R10Button from '../R10Button.vue';
import R10Notice from '../R10Notice.vue';
import ScenePlayback from '../ScenePlayback.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();
const page = usePage<{ auth: { user: User | null } }>();

const props = withDefaults(
    defineProps<{
        submitting: boolean;
        /** Whether the save in flight is a draft save rather than a hand-in. */
        savingDraft: boolean;
        justSubmitted: boolean;
        /** Whether the plan was just written down as a draft. */
        justSavedDraft: boolean;
        /**
         * Whether the submission just made was an update to a plan the crew
         * already held, rather than a first hand-in. Read from the status the
         * plan carried before the save, so it does not change under the notice
         * once that save has moved the plan to Submitted.
         */
        justUpdated: boolean;
        saveError: string;
        publicLink: string;
        linkCopied: boolean;
        aiLoading: boolean;
        aiResult: string;
        aiError: string;
        /**
         * A plan opened by its share link without the right to change it: the
         * document is all there is, and everything that would write to the
         * plan — or spend the house's money on an AI review — is gone with it.
         */
        readOnly?: boolean;
    }>(),
    { readOnly: false },
);

defineEmits<{
    submit: [];
    'save-draft': [];
    download: [];
    'create-link': [];
    'copy-link': [];
    'ai-review': [];
}>();

/**
 * The document the performer reads, rendered by the same rules the mail uses —
 * see `presentPlan()` and `App\Http\Resources\PlanDocument`.
 *
 * A saved plan names its own author as the contact, so a technician or a
 * team-mate opening it reads the same document the mail sent out. The signed-in
 * user stands in only for a plan that has no author yet: the one being written
 * here, which this user is about to hand in.
 */
const doc = computed(() =>
    presentPlan(plan, plan.authorEmail ?? page.props.auth.user?.email ?? null),
);

/** Whether the technician's focused scene-by-scene view is open. */
const playbackOpen = ref(false);

/**
 * A plan the technical team is already holding is not handed in again — it is
 * updated — and the button says so, so nobody presses it expecting the crew to
 * be told all over again.
 */
const alreadySubmitted = computed(() => isDelivered(plan.status));

const submitLabel = computed(() => {
    if (alreadySubmitted.value) {
        return props.submitting ? 'Uuendan…' : 'Uuenda esitatud plaani';
    }

    return props.submitting ? 'Esitan…' : 'Esita tehnikutiimile';
});

/** The icon says what the label says: a hand-in sends, an update refreshes. */
const submitIcon = computed(() => (alreadySubmitted.value ? RefreshCw : Send));

/**
 * A plan the crew is holding is not saved as a draft any more — the only thing
 * left to do with it is update it — so the draft button goes once it has been
 * handed in. See {@see canSaveDraft}.
 */
const showDraftButton = computed(() => canSaveDraft(plan));

const draftLabel = computed(() => {
    if (props.savingDraft) {
        return 'Salvestan…';
    }

    return plan.token ? 'Uuenda mustandit' : 'Salvesta mustand';
});

/** Whether either save is in flight, and so both buttons are shut. */
const busy = computed(() => props.submitting || props.savingDraft);

/**
 * Whether the plan on screen is saved but still nobody's but its author's. A
 * saved draft looks finished — it has a link, it prints, it is in the plans
 * listing — so for as long as it is one, the review page says outright that the
 * technical team has not been handed it.
 */
const savedAsDraft = computed(
    () => !props.readOnly && showDraftButton.value && Boolean(plan.token),
);
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <div class="r10-no-print">
            <StepHeader
                v-if="readOnly"
                eyebrow="Ülevaade"
                title="Etenduse tehnikaplaan"
                lead="See tehnikaplaan on jagatud avaliku lingi kaudu."
            />

            <StepHeader
                v-else
                eyebrow="Samm 7 / 7 · Ülevaade"
                title="Vaata üle & saada"
                lead="Kontrolli plaan üle. Salvesta see mustandina, et hiljem edasi teha, või esita tehnikutiimile. Plaani saab ka PDF-ina alla laadida või jagatava lingina välja saata."
            />

            <R10Notice v-if="readOnly" class="mb-6">
                Avaliku lingiga jagatud tehnikaplaane saad ainult lugeda. Plaani
                sisu muutmiseks palun logi sisse.
            </R10Notice>
        </div>

        <!-- Printable document -->
        <PlanDocument :doc="doc">
            <template #scenes-action>
                <R10Button
                    variant="outline"
                    size="sm"
                    title="Tehniku vaade"
                    aria-label="Tehniku vaade"
                    class="r10-no-print ml-auto"
                    @click="playbackOpen = true"
                >
                    <Spotlight class="h-4 w-4" />
                </R10Button>
            </template>
        </PlanDocument>

        <!-- A saved draft is nobody's but its author's until the button below
             is pressed, and this is what says so. -->
        <R10Notice
            v-if="savedAsDraft"
            class="r10-no-print mt-[26px]"
            data-test="draft-warning"
        >
            <div class="mb-0.5 font-r10-body text-sm font-bold">
                <template v-if="justSavedDraft">
                    Mustand on salvestatud, aga tehnikutiimile ei ole seda veel
                    esitatud.
                </template>
                <template v-else>
                    See plaan on mustand — tehnikutiim ei ole seda veel saanud.
                </template>
            </div>
            <div>
                Kui plaan on valmis, vajuta „Esita tehnikutiimile“ — alles siis
                saab tehnikutiim sellest teate.
            </div>
        </R10Notice>

        <!-- Actions -->
        <div class="r10-no-print mt-[26px] flex flex-wrap gap-3.5">
            <!-- Icons trail the label, as they do on the plans listing. They
                 label nothing the words do not already say, so they stay
                 `aria-hidden` and the button reads the same to a screen
                 reader as it did without them. -->
            <R10Button variant="outline" size="lg" @click="$emit('download')">
                Ava PDF
                <Download class="h-4 w-4" aria-hidden="true" />
            </R10Button>
            <template v-if="!readOnly">
                <R10Button
                    variant="outline"
                    size="md"
                    :disabled="busy"
                    @click="$emit('create-link')"
                >
                    Jagatav link
                    <Link2 class="h-4 w-4" aria-hidden="true" />
                </R10Button>
                <R10Button
                    variant="outline"
                    size="md"
                    :disabled="aiLoading"
                    @click="$emit('ai-review')"
                >
                    AI ülevaatus
                    <Sparkles class="h-4 w-4" aria-hidden="true" />
                </R10Button>
                <R10Button
                    v-if="showDraftButton"
                    variant="outline"
                    size="md"
                    :disabled="busy"
                    data-test="save-draft"
                    @click="$emit('save-draft')"
                >
                    {{ draftLabel }}
                    <Save class="h-4 w-4" aria-hidden="true" />
                </R10Button>
                <R10Button
                    variant="primary"
                    size="md"
                    :disabled="busy"
                    data-test="submit-plan"
                    @click="$emit('submit')"
                >
                    {{ submitLabel }}
                    <component
                        :is="submitIcon"
                        class="h-4 w-4"
                        aria-hidden="true"
                    />
                </R10Button>
            </template>
        </div>

        <R10Notice v-if="saveError" class="r10-no-print mt-4">
            {{ saveError }}
        </R10Notice>

        <R10Notice v-if="aiLoading" tone="busy" class="r10-no-print mt-4">
            AI vaatab praegu plaani sisu üle, oota…
        </R10Notice>

        <R10Notice v-if="aiError" class="r10-no-print mt-4">
            {{ aiError }}
        </R10Notice>

        <div
            v-if="aiResult"
            class="r10-no-print mt-4 overflow-hidden rounded-[14px] border border-r10-grey-200"
        >
            <div
                class="flex flex-wrap items-center gap-2.5 bg-r10-navy px-4 py-3.5 sm:px-5"
            >
                <Diamond :size="10" />
                <span
                    class="font-r10-display text-sm font-semibold tracking-[0.04em] text-white uppercase"
                >
                    AI soovitused
                </span>
                <button
                    type="button"
                    class="ml-auto shrink-0 cursor-pointer rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 font-r10-body text-[11px] font-bold tracking-[0.06em] text-white uppercase"
                    @click="$emit('ai-review')"
                >
                    Kontrolli uuesti
                </button>
            </div>
            <div
                class="markdown bg-white px-4 py-5 font-r10-body text-sm leading-relaxed break-words text-r10-ink sm:px-[22px]"
                v-html="aiResult"
            ></div>
            <div
                class="bg-white px-4 pb-4 text-xs text-r10-grey-500 sm:px-[22px]"
            >
                See on AI-genereeritud soovitus ja ei pruugi olla täpne. AI
                soovitused ei ole kohustus plaani muuta.
            </div>
        </div>

        <R10Notice
            v-if="justSubmitted"
            tone="success"
            class="r10-no-print mt-4"
        >
            <div v-if="justUpdated">
                <div class="mb-0.5 font-r10-body text-sm font-bold text-white">
                    Esitatud plaan on uuendatud.
                </div>
                <div class="text-[13px] leading-normal text-r10-navy-200">
                    Tehnikutiimile uut teadet ei saadetud — nad näevad plaani
                    lingilt alati kõige värskemat versiooni.
                </div>
            </div>
            <div v-else>
                <div class="mb-0.5 font-r10-body text-sm font-bold text-white">
                    Plaan on esitatud tehnikutiimile.
                </div>
                <div class="text-[13px] leading-normal text-r10-navy-200">
                    Tehnikutiim saab plaanist teate ja võtab vajadusel ühendust.
                    Staatus:
                    <strong class="text-white">Esitatud</strong>.
                </div>
            </div>
        </R10Notice>

        <div
            v-if="publicLink"
            class="r10-no-print mt-4 rounded-[14px] border border-r10-grey-200 bg-r10-grey-100 px-4 py-5 sm:px-[22px]"
        >
            <div class="mb-2 flex items-center gap-2">
                <Diamond :size="9" />
                <span
                    class="font-r10-body text-xs font-semibold tracking-[0.12em] text-r10-ink uppercase"
                >
                    Avalik link
                </span>
            </div>
            <p class="mt-0 mb-3 max-w-[66ch] text-[13px] text-r10-grey-500">
                See link avab tehnikaplaani täidetud kujul. Muudatused
                salvestatakse üle.
            </p>
            <div class="flex flex-wrap items-center gap-2.5">
                <input
                    type="text"
                    readonly
                    :value="publicLink"
                    class="min-w-0 shrink grow basis-full rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-mono text-xs text-r10-ink outline-none sm:basis-0"
                />
                <button
                    type="button"
                    class="shrink-0 cursor-pointer rounded-full border-2 border-r10-navy bg-white px-[22px] py-2.5 font-r10-body text-[13px] font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:bg-r10-navy hover:text-white"
                    @click="$emit('copy-link')"
                >
                    Kopeeri link
                </button>
                <span
                    v-if="linkCopied"
                    class="text-[13px] font-bold text-r10-navy"
                    >Kopeeritud!</span
                >
            </div>
        </div>

        <ScenePlayback v-if="playbackOpen" @close="playbackOpen = false" />
    </section>
</template>
