<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Spotlight } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { User } from '@/types';
import Diamond from '../Diamond.vue';
import { formatFileSize } from '../plan';
import { usePlan } from '../planKey';
import R10Button from '../R10Button.vue';
import ScenePlayback from '../ScenePlayback.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();
const page = usePage<{ auth: { user: User | null } }>();

defineProps<{
    submitting: boolean;
    justSubmitted: boolean;
    publicLink: string;
    linkCopied: boolean;
    aiLoading: boolean;
    aiResult: string;
    aiError: string;
}>();

defineEmits<{
    submit: [];
    download: [];
    'create-link': [];
    'copy-link': [];
    'ai-review': [];
}>();

const STATUS_LABELS: Record<string, string> = {
    draft: 'Mustand',
    submitted: 'Esitatud',
};

const dash = (value: unknown): string =>
    value != null && String(value).trim() !== '' ? String(value) : '—';

const contactLine = computed(() => page.props.auth.user?.email ?? '—');

const micsSummary = computed(() =>
    plan.sound.micsMode === 'yes'
        ? 'Jah' + (plan.sound.micsDetail ? ' — ' + plan.sound.micsDetail : '')
        : 'Ei',
);

const musicianSummary = computed(() =>
    plan.sound.musicianMode === 'yes'
        ? 'Jah' +
          (plan.sound.musicianDetail ? ' — ' + plan.sound.musicianDetail : '')
        : 'Ei',
);

const smokeSummary = computed(() => {
    const s = plan.equipment.smoke;

    return s === 'no'
        ? 'Ei tohi'
        : s === 'yes'
          ? 'Jah'
          : 'Jah, kuid minimaalselt';
});

const suggestionsLine = computed(
    () =>
        (plan.equipment.suggestions === 'yes' ? 'Jah' : 'Ei') +
        (plan.equipment.suggestNote.trim()
            ? ' — ' + plan.equipment.suggestNote
            : ''),
);

const reviewEquip = computed(() =>
    plan.equipment.items.filter((it) => it.name.trim() || it.use.trim()),
);

const reviewScenes = computed(() =>
    plan.scenes.map((s, i) => ({
        num: i + 1,
        name: dash(s.name),
        light: dash(s.light),
        // The uploaded file is listed on its own line so it stays clickable.
        soundFile: s.soundFile?.status === 'ready' ? s.soundFile : null,
        sound: [s.soundUrl, s.sound].filter((v) => v && v.trim()).join('\n'),
        notes: dash(s.notes),
    })),
);

const statusLabel = computed(
    () => STATUS_LABELS[plan.status] ?? STATUS_LABELS.draft,
);

const durationLabel = computed(() =>
    plan.meta.duration ? plan.meta.duration + ' min' : '—',
);

/** Whether the technician's focused scene-by-scene view is open. */
const playbackOpen = ref(false);

const cellClass = 'border border-r10-grey-200 px-3 py-2 align-top';
const headCellClass =
    'border border-r10-navy bg-r10-navy px-2.5 py-2 text-left font-bold text-white';
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <div class="r10-no-print">
            <StepHeader
                eyebrow="Samm 7 / 7 · Ülevaade"
                title="Vaata üle & saada"
                lead="Kontrolli plaan üle. Seejärel esita see tehnikutiimile, laadi PDF-ina alla või loo jagatav link."
            />
        </div>

        <!-- Printable document -->
        <div
            class="r10-print-doc rounded-xl border border-r10-grey-200 bg-white p-10 text-r10-ink"
        >
            <div
                class="mb-6 flex items-start justify-between gap-5 border-b-[3px] border-r10-navy pb-[18px]"
            >
                <div>
                    <div class="mb-1.5 flex items-center gap-2">
                        <Diamond :size="11" />
                        <span
                            class="font-r10-body text-[11px] font-bold tracking-[0.18em] text-r10-orange uppercase"
                        >
                            Ruutu10 · Tehnikaplaan
                        </span>
                    </div>
                    <div
                        class="font-r10-display text-[26px] leading-[1.05] font-bold tracking-[0.02em] text-r10-navy uppercase"
                    >
                        {{ dash(plan.meta.showName) }}
                    </div>
                </div>
                <div
                    class="shrink-0 text-right text-[13px] leading-relaxed text-r10-grey-500"
                >
                    <div
                        class="mb-2 inline-flex items-center gap-1.5 font-r10-body text-[11px] font-bold tracking-[0.1em] text-r10-orange uppercase"
                    >
                        <span
                            class="h-[7px] w-[7px] rotate-45 rounded-[1px] bg-current"
                        />
                        {{ statusLabel }}
                    </div>
                    <div>
                        <span class="font-bold text-r10-navy">Etendus:</span>
                        {{ dash(plan.meta.showDate) }}
                    </div>
                    <div><span class="font-bold text-r10-navy">Kestus: </span>
                       <span class="font-mono text-xs"> {{ durationLabel }}</span></div>
                    <!-- Only a saved plan has a key; it is what the wizard
                         reopens the plan by, so it belongs on the printout. -->
                    <div v-if="plan.token" class="mt-1">
                        <span class="font-bold text-r10-navy">Plaani võti: </span>
                        <span class="font-mono text-xs">{{ plan.token }}</span>
                    </div>
                </div>
            </div>

            <div
                class="mb-3 font-r10-display text-sm font-semibold tracking-[0.04em] text-r10-navy uppercase"
            >
                Etendus
            </div>
            <table class="mb-[26px] w-full border-collapse text-sm">
                <tbody>
                    <tr>
                        <td
                            :class="[
                                cellClass,
                                'w-[34%] bg-r10-grey-100 font-bold',
                            ]"
                        >
                            Esineja
                        </td>
                        <td :class="cellClass">
                            {{ dash(plan.meta.performer) }}
                        </td>
                    </tr>
                    <tr>
                        <td :class="[cellClass, 'bg-r10-grey-100 font-bold']">
                            Kontakt
                        </td>
                        <td :class="cellClass">{{ contactLine }}</td>
                    </tr>
                    <tr>
                        <td :class="[cellClass, 'bg-r10-grey-100 font-bold']">
                            Lühikirjeldus
                        </td>
                        <td :class="[cellClass, 'whitespace-pre-line']">
                            {{ dash(plan.meta.description) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                class="mb-3 font-r10-display text-sm font-semibold tracking-[0.04em] text-r10-navy uppercase"
            >
                Heliplaan
            </div>
            <table class="mb-[26px] w-full border-collapse text-sm">
                <tbody>
                    <tr>
                        <td
                            :class="[
                                cellClass,
                                'w-[34%] bg-r10-grey-100 font-bold',
                            ]"
                        >
                            Mikrofonid
                        </td>
                        <td :class="cellClass">{{ micsSummary }}</td>
                    </tr>
                    <tr>
                        <td :class="[cellClass, 'bg-r10-grey-100 font-bold']">
                            Oma muusik
                        </td>
                        <td :class="cellClass">{{ musicianSummary }}</td>
                    </tr>
                </tbody>
            </table>

            <div
                class="mb-3 flex flex-wrap items-center gap-3 font-r10-display text-sm font-semibold tracking-[0.04em] text-r10-navy uppercase"
            >
                Stseenid
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
            </div>
            <div class="mb-[26px] overflow-x-auto">
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr>
                            <th :class="[headCellClass, 'text-center']">Nr</th>
                            <th :class="headCellClass">Nimi</th>
                            <th :class="headCellClass">Valgus</th>
                            <th :class="headCellClass">Heli</th>
                            <th :class="headCellClass">Märkmed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in reviewScenes" :key="row.num">
                            <td
                                :class="[
                                    cellClass,
                                    'text-center font-bold text-r10-navy',
                                ]"
                            >
                                {{ row.num }}
                            </td>
                            <td :class="[cellClass, 'font-bold break-words']">
                                {{ row.name }}
                            </td>
                            <td
                                :class="[
                                    cellClass,
                                    'break-words whitespace-pre-line',
                                ]"
                            >
                                {{ row.light }}
                            </td>
                            <td
                                :class="[
                                    cellClass,
                                    'break-words whitespace-pre-line',
                                ]"
                            >
                                <span v-if="row.soundFile" class="block">
                                    <a
                                        :href="row.soundFile.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange"
                                    >
                                        {{ row.soundFile.name }}
                                    </a>
                                    ({{ formatFileSize(row.soundFile.size) }})
                                </span>
                                <span v-if="row.sound || !row.soundFile">
                                    {{ dash(row.sound) }}
                                </span>
                            </td>
                            <td
                                :class="[
                                    cellClass,
                                    'break-words whitespace-pre-line',
                                ]"
                            >
                                {{ row.notes }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="mb-3 font-r10-display text-sm font-semibold tracking-[0.04em] text-r10-navy uppercase"
            >
                Erivahendid & load
            </div>
            <table class="mb-[26px] w-full border-collapse text-sm">
                <tbody>
                    <tr v-for="(item, index) in reviewEquip" :key="index">
                        <td
                            :class="[
                                cellClass,
                                'w-[34%] bg-r10-grey-100 font-bold',
                            ]"
                        >
                            {{ dash(item.name) }}
                        </td>
                        <td :class="cellClass">{{ dash(item.use) }}</td>
                    </tr>
                    <tr>
                        <td :class="[cellClass, 'bg-r10-grey-100 font-bold']">
                            Suitsuefektid
                        </td>
                        <td :class="cellClass">{{ smokeSummary }}</td>
                    </tr>
                    <tr>
                        <td :class="[cellClass, 'bg-r10-grey-100 font-bold']">
                            Tehniku pakkumised
                        </td>
                        <td :class="[cellClass, 'whitespace-pre-line']">
                            {{ suggestionsLine }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                class="mb-3 font-r10-display text-sm font-semibold tracking-[0.04em] text-r10-navy uppercase"
            >
                Lisainfo
            </div>
            <div
                class="mb-3 rounded-lg border border-r10-grey-200 px-3.5 py-3 text-sm leading-relaxed whitespace-pre-line"
            >
                {{ dash(plan.extra.notes) }}
            </div>
            <div
                v-if="plan.extra.files.length"
                class="text-[13px] text-r10-grey-500"
            >
                Manused:
                <span
                    v-for="(file, index) in plan.extra.files"
                    :key="file.id || index"
                    class="mt-1 flex items-center gap-3"
                >
                    <span class="text-r10-ink"
                        >{{ file.name }} ({{ formatFileSize(file.size) }})</span
                    >
                    <template v-if="file.url">
                        <a
                            :href="file.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange"
                            >Ava</a
                        >
                        <a
                            v-if="file.downloadUrl"
                            :href="file.downloadUrl"
                            class="text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange"
                            >Laadi alla</a
                        >
                    </template>
                </span>
            </div>
        </div>

        <!-- Actions -->
        <div class="r10-no-print mt-[26px] flex flex-wrap gap-3.5">

            <R10Button variant="outline" size="lg" @click="$emit('download')"
                >Laadi alla PDF</R10Button
            >
            <R10Button
                variant="outline"
                size="lg"
                :disabled="submitting"
                @click="$emit('create-link')"
            >
                avalik link
            </R10Button>
            <R10Button
                variant="outline"
                size="lg"
                :disabled="aiLoading"
                @click="$emit('ai-review')"
            >
                AI ülevaatus
            </R10Button>
             <R10Button
                variant="primary"
                size="lg"
                :disabled="submitting"
                @click="$emit('submit')"
            >
                {{ submitting ? 'Esitan…' : 'Esita tehnikutiimile' }}
            </R10Button>
        </div>

        <div
            v-if="aiLoading"
            class="r10-no-print mt-4 flex items-center gap-3 rounded-[14px] border border-r10-grey-200 bg-r10-grey-100 px-5 py-4"
        >
            <span
                class="h-2.5 w-2.5 shrink-0 rotate-45 animate-[r10spin_1s_linear_infinite] rounded-[1px] bg-r10-orange"
            />
            <span class="text-sm text-r10-grey-700"
                >AI vaatab praegu plaani sisu üle, oota…</span
            >
        </div>

        <div
            v-if="aiError"
            class="r10-no-print mt-4 flex items-start gap-2.5 rounded-[14px] border border-r10-orange bg-r10-orange-100 px-[18px] py-3.5"
        >
            <span
                class="mt-[5px] h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px] bg-r10-error"
            />
            <span class="text-sm leading-normal text-r10-navy">{{
                aiError
            }}</span>
        </div>

        <div
            v-if="aiResult"
            class="r10-no-print mt-4 overflow-hidden rounded-[14px] border border-r10-grey-200"
        >
            <div class="flex items-center gap-2.5 bg-r10-navy px-5 py-3.5">
                <Diamond :size="10" />
                <span
                    class="font-r10-display text-sm font-semibold tracking-[0.04em] text-white uppercase"
                >
                    AI soovitused
                </span>
                <button
                    type="button"
                    class="ml-auto cursor-pointer rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 font-r10-body text-[11px] font-bold tracking-[0.06em] text-white uppercase"
                    @click="$emit('ai-review')"
                >
                    Kontrolli uuesti
                </button>
            </div>
            <div
                class="markdown bg-white px-[22px] py-5 font-r10-body text-sm leading-relaxed text-r10-ink"
                v-html="aiResult"
            ></div>
            <div class="bg-white px-[22px] pb-4 text-xs text-r10-grey-500">
                See on AI-genereeritud soovitus ja ei pruugi olla täpne. AI
                soovitused ei ole kohustus plaani muuta.
            </div>
        </div>

        <div
            v-if="justSubmitted"
            class="r10-no-print mt-4 flex items-start gap-3 rounded-[14px] bg-r10-navy px-5 py-4"
        >
            <span
                class="mt-[5px] h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px] bg-r10-orange"
            />
            <div>
                <div class="mb-0.5 font-r10-body text-sm font-bold text-white">
                    Plaan on esitatud tehnikutiimile.
                </div>
                <div class="text-[13px] leading-normal text-r10-navy-200">
                    Tehnikutiim saab plaanist teate ja võtab vajadusel ühendust.
                    Staatus:
                    <strong class="text-white">Esitatud</strong>.
                </div>
            </div>
        </div>

        <div
            v-if="publicLink"
            class="r10-no-print mt-4 rounded-[14px] border border-r10-grey-200 bg-r10-grey-100 px-[22px] py-5"
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
                See link avab tehnikaplaani täidetud kujul. Muudatused salvestatakse üle.
            </p>
            <div class="flex flex-wrap items-center gap-2.5">
                <input
                    type="text"
                    readonly
                    :value="publicLink"
                    class="min-w-0 flex-1 rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-mono text-xs text-r10-ink outline-none"
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
