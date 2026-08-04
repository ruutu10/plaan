<script setup lang="ts">
import type { PlanDocument } from '@/types/technicalPlan';
import Diamond from './Diamond.vue';

/**
 * The plan as the performer and the technician read it: on screen at the end of
 * the wizard, and — through `.r10-print-doc` — as the printout.
 *
 * Every value arrives already rendered by `presentPlan()`. The mail shows the
 * same document from the same values via `App\Http\Resources\PlanDocument`; the
 * markup differs because a mail has to be an inline-styled table, but no
 * decision about *how a value reads* is taken here or there.
 */
defineProps<{ doc: PlanDocument }>();

const cellClass = 'border border-r10-grey-200 px-3 py-2 align-top break-words';
const labelCellClass = `${cellClass} bg-r10-grey-100 font-bold`;
const headCellClass =
    'border border-r10-navy bg-r10-navy px-2.5 py-2 text-left font-bold text-white';
const sectionTitleClass =
    'mb-3 font-r10-display text-sm font-semibold tracking-[0.04em] text-r10-navy uppercase';
const linkClass =
    'text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange';
</script>

<template>
    <div
        class="r10-print-doc rounded-xl border border-r10-grey-200 bg-white p-4 text-r10-ink sm:p-6 lg:p-10"
    >
        <div
            class="mb-6 flex flex-col gap-4 border-b-[3px] border-r10-navy pb-[18px] sm:flex-row sm:items-start sm:justify-between sm:gap-5"
        >
            <div class="min-w-0">
                <div class="mb-1.5 flex items-center gap-2">
                    <Diamond :size="11" />
                    <span
                        class="font-r10-body text-[11px] font-bold tracking-[0.18em] text-r10-orange uppercase"
                    >
                        Ruutu10 · Tehnikaplaan
                    </span>
                </div>
                <div
                    class="font-r10-display text-[22px] leading-[1.05] font-bold tracking-[0.02em] break-words text-r10-navy uppercase sm:text-[26px]"
                >
                    {{ doc.formatName }}
                </div>
            </div>
            <div
                class="text-[13px] leading-relaxed text-r10-grey-500 sm:shrink-0 sm:text-right"
            >
                <div
                    class="mb-2 inline-flex items-center gap-1.5 font-r10-body text-[11px] font-bold tracking-[0.1em] text-r10-orange uppercase"
                >
                    <span
                        class="h-[7px] w-[7px] rotate-45 rounded-[1px] bg-current"
                    />
                    {{ doc.statusLabel }}
                </div>
                <div>
                    <span class="font-bold text-r10-navy">Etendus:</span>
                    {{ doc.performanceDate }}
                </div>
                <div>
                    <span class="font-bold text-r10-navy">Kestus: </span>
                    <span class="font-mono text-xs">{{
                        doc.durationLabel
                    }}</span>
                </div>
                <!-- Only a saved plan has a key; it is what the wizard reopens
                     the plan by, so it belongs on the printout. -->
                <div v-if="doc.token" class="mt-1">
                    <span class="font-bold text-r10-navy">Plaani võti: </span>
                    <span class="font-mono text-xs break-all">{{
                        doc.token
                    }}</span>
                </div>
            </div>
        </div>

        <div :class="sectionTitleClass">Etendus</div>
        <table class="mb-[26px] w-full border-collapse text-sm">
            <tbody>
                <tr>
                    <td :class="[labelCellClass, 'w-[34%]']">Esineja</td>
                    <td :class="cellClass">{{ doc.performer }}</td>
                </tr>
                <tr>
                    <td :class="labelCellClass">Kontakt</td>
                    <td :class="cellClass">{{ doc.contact }}</td>
                </tr>
                <tr>
                    <td :class="labelCellClass">Lühikirjeldus</td>
                    <td :class="[cellClass, 'whitespace-pre-line']">
                        {{ doc.description }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div :class="sectionTitleClass">Heliplaan</div>
        <table class="mb-[26px] w-full border-collapse text-sm">
            <tbody>
                <tr>
                    <td :class="[labelCellClass, 'w-[34%]']">Mikrofonid</td>
                    <td :class="[cellClass, 'whitespace-pre-line']">
                        {{ doc.micsSummary }}
                    </td>
                </tr>
                <tr>
                    <td :class="labelCellClass">Oma muusik</td>
                    <td :class="[cellClass, 'whitespace-pre-line']">
                        {{ doc.musicianSummary }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div
            :class="[
                sectionTitleClass,
                'mb-3 flex flex-wrap items-center gap-3',
            ]"
        >
            Stseenid
            <!-- The technician's playback view is an action, not part of the
                 document, so it never reaches the printout. -->
            <slot name="scenes-action" />
        </div>
        <!-- Five columns never fit a phone: the table scrolls sideways within
             the document rather than squeezing every cue to one word a line. -->
        <div class="mb-[26px] overflow-x-auto">
            <table class="w-full min-w-[560px] border-collapse text-[13px]">
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
                    <tr v-for="scene in doc.scenes" :key="scene.num">
                        <td
                            :class="[
                                cellClass,
                                'text-center font-bold text-r10-navy',
                            ]"
                        >
                            {{ scene.num }}
                        </td>
                        <td :class="[cellClass, 'font-bold break-words']">
                            {{ scene.name }}
                        </td>
                        <td
                            :class="[
                                cellClass,
                                'break-words whitespace-pre-line',
                            ]"
                        >
                            {{ scene.light }}
                        </td>
                        <td :class="[cellClass, 'break-words']">
                            <!-- The uploaded file and the link each get their
                                 own line so they stay clickable. -->
                            <span v-if="scene.soundFile" class="block">
                                <a
                                    :href="scene.soundFile.url ?? undefined"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :class="linkClass"
                                >
                                    {{ scene.soundFile.name }}
                                </a>
                                ({{ scene.soundFile.sizeLabel }})
                            </span>
                            <span v-if="scene.soundUrl" class="block">
                                <a
                                    :href="scene.soundUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :class="[linkClass, 'break-all']"
                                >
                                    {{ scene.soundUrl }}
                                </a>
                            </span>
                            <span
                                v-if="scene.soundText"
                                class="block break-words whitespace-pre-line"
                            >
                                {{ scene.soundText }}
                            </span>
                        </td>
                        <td
                            :class="[
                                cellClass,
                                'break-words whitespace-pre-line',
                            ]"
                        >
                            {{ scene.notes }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div :class="sectionTitleClass">Erivahendid & load</div>
        <table class="mb-[26px] w-full border-collapse text-sm">
            <tbody>
                <tr v-for="(item, index) in doc.equipmentItems" :key="index">
                    <td :class="[labelCellClass, 'w-[34%]']">
                        {{ item.name }}
                    </td>
                    <td :class="cellClass">{{ item.use }}</td>
                </tr>
                <tr>
                    <td :class="labelCellClass">Suitsuefektid</td>
                    <td :class="cellClass">{{ doc.smokeSummary }}</td>
                </tr>
                <tr>
                    <td :class="labelCellClass">Tehniku pakkumised</td>
                    <td :class="[cellClass, 'whitespace-pre-line']">
                        {{ doc.suggestionsLine }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div :class="sectionTitleClass">Lisainfo</div>
        <div
            class="mb-3 rounded-lg border border-r10-grey-200 px-3.5 py-3 text-sm leading-relaxed whitespace-pre-line"
        >
            {{ doc.notes }}
        </div>
        <div v-if="doc.files.length" class="text-[13px] text-r10-grey-500">
            Manused:
            <span
                v-for="(file, index) in doc.files"
                :key="index"
                class="mt-1 flex flex-wrap items-center gap-x-3"
            >
                <span class="break-all text-r10-ink"
                    >{{ file.name }} ({{ file.sizeLabel }})</span
                >
                <template v-if="file.url">
                    <a
                        :href="file.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :class="linkClass"
                        >Ava</a
                    >
                    <a
                        v-if="file.downloadUrl"
                        :href="file.downloadUrl"
                        :class="linkClass"
                        >Laadi alla</a
                    >
                </template>
            </span>
        </div>
    </div>
</template>
