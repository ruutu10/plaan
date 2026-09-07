<script setup lang="ts">
import { useEventListener, useNow } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatVenueClockTime } from '@/lib/date';
import { hideFeedbackWidget, showFeedbackWidget } from '@/lib/sentry';
import Diamond from './Diamond.vue';
import { formatFileSize, intermissionLabel, soundAudioUrl } from './plan';
import { usePlan } from './planKey';
import { normaliseScenes } from './presentPlan';
import SceneAudio from './SceneAudio.vue';
import { showSchedule } from './showSchedule';

const plan = usePlan();

const emit = defineEmits<{ close: [] }>();

/**
 * Scenes keep the numbering the review table and the printout use, so a cue
 * called out as "stseen 4" is the fourth row everywhere — hence the shared
 * `normaliseScenes()`. The wording stays this view's own: a blank cue is left
 * blank here rather than stood in with an em dash, because this is what the
 * technician reads off during the show.
 */
const scenes = computed(() =>
    normaliseScenes(plan).map((scene) => ({
        ...scene,
        // Each cue gets its own player. `audio` is null for sound that only
        // exists behind a link no player can read — that keeps its link line.
        sounds: scene.sounds.map((sound) => ({
            ...sound,
            audio: soundAudioUrl(sound),
        })),
    })),
);

/**
 * How many of the steps are scenes. The interval is stepped through like the
 * rest — the tech stands at it while the house is out — but it is not one of
 * the numbered scenes, so it is not counted in "stseen 4 / 12" either.
 */
const sceneCount = computed(
    () => scenes.value.filter((scene) => scene.intermission === 0).length,
);

const active = ref(0);

const activeScene = computed(() => scenes.value[active.value] ?? null);

/** What the header and the footer call the step the tech is standing on. */
const stepLabel = computed(() => {
    const scene = activeScene.value;

    if (!scene) {
        return '';
    }

    return scene.intermission > 0
        ? intermissionLabel(scene.intermission)
        : `Stseen ${scene.num} / ${sceneCount.value}`;
});

const hasPrevious = computed(() => active.value > 0);
const hasNext = computed(() => active.value < scenes.value.length - 1);

function go(index: number): void {
    active.value = Math.min(Math.max(index, 0), scenes.value.length - 1);
}

function previous(): void {
    go(active.value - 1);
}

function next(): void {
    go(active.value + 1);
}

/** Keep the cursor inside the list when scenes are added or removed. */
watch(
    () => scenes.value.length,
    (length) => {
        if (active.value > length - 1) {
            active.value = Math.max(length - 1, 0);
        }
    },
);

const navRef = ref<HTMLElement | null>(null);
const mainRef = ref<HTMLElement | null>(null);

// Advancing by keyboard must bring the new scene into view on both sides.
watch(active, (index) => {
    mainRef.value?.scrollTo({ top: 0 });
    navRef.value?.children[index]?.scrollIntoView({ block: 'nearest' });
});

/** The tech drives this view one-handed, so cues advance on the arrow keys. */
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null;

    if (target?.closest('input, textarea, select')) {
        return;
    }

    // Space activates whatever control has focus — the audio player's play
    // button above all — so it only advances scenes when nothing is focused.
    if (event.key === ' ' && target?.closest('button, a')) {
        return;
    }

    if (['ArrowRight', 'ArrowDown', 'PageDown', ' '].includes(event.key)) {
        event.preventDefault();
        next();
    } else if (['ArrowLeft', 'ArrowUp', 'PageUp'].includes(event.key)) {
        event.preventDefault();
        previous();
    } else if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
    }
});

// The overlay covers the page; letting the wizard scroll underneath it only
// loses the tech's place in the plan when they close the view.
const previousOverflow = ref('');

onMounted(() => {
    previousOverflow.value = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    // The trigger button floats over this overlay otherwise, inviting bug
    // reports mid-cue instead of once the tech is back in the wizard.
    hideFeedbackWidget();
});

onBeforeUnmount(() => {
    document.body.style.overflow = previousOverflow.value;
    showFeedbackWidget();
});

function sceneLabel(name: string): string {
    return name || 'Nimeta stseen';
}

/* ---- Wall clock ------------------------------------------------------ */

// Ticks every second; `useNow` stops its timer when the view closes.
const now = useNow({ interval: 1000 });

const clock = computed(() => formatVenueClockTime(now.value));

const clockDateTime = computed(() => now.value.toISOString());

/**
 * The slot the show is meant to fill. Read under the clock, it is what tells
 * the tech whether the act is on its way out on time — and once the end has
 * gone by, the whole panel inverts rather than making them do the sum.
 */
const schedule = computed(() => showSchedule(plan.meta, now.value));

/**
 * Running over turns the clock panel inside out: the orange the rest of the
 * view spends on accents fills it, and the navy the view is built on becomes
 * the ink. It is the one block on screen that changes shape, so the tech
 * catches it from across the booth instead of reading the small print.
 */
const overrunning = computed(() => schedule.value?.overrunning === true);

const clockLabelClass = computed(() =>
    overrunning.value ? 'text-r10-navy/70' : 'text-r10-navy-300',
);

const cueLabelClass =
    'font-r10-body text-[11px] font-bold tracking-[0.18em] text-r10-orange uppercase';
const cueBodyClass =
    'mt-2 font-r10-body text-lg leading-relaxed break-words whitespace-pre-line text-white';
const cueLinkClass =
    'text-r10-orange underline decoration-r10-orange/40 transition hover:decoration-r10-orange';
</script>

<template>
    <div
        class="r10-no-print fixed inset-0 z-50 flex flex-col bg-r10-navy-900 text-white"
        role="dialog"
        aria-modal="true"
        aria-label="Tehniku vaade"
    >
        <!-- Equal 1fr flanks keep the middle cell on the true centre line
             whatever the show name or close button measure. -->
        <header
            class="grid shrink-0 grid-cols-[1fr_auto_1fr] items-center gap-3 border-b border-white/15 bg-r10-navy px-4 py-2.5 sm:gap-4 sm:px-5 sm:py-3"
        >
            <div class="flex min-w-0 items-center gap-2.5">
                <Diamond :size="11" />
                <span
                    class="hidden shrink-0 font-r10-body text-[11px] font-bold tracking-[0.18em] text-r10-orange uppercase sm:inline"
                >
                    Tehniku vaade
                </span>
                <span
                    class="hidden min-w-0 truncate font-r10-display text-[15px] font-semibold tracking-[0.03em] text-r10-navy-200 uppercase xl:block"
                >
                    {{ plan.meta.formatName || 'Nimeta etendus' }}
                </span>
            </div>

            <!-- The scene the tech is on titles the view, leaving the body to
                 the cues themselves. -->
            <div
                v-if="activeScene"
                :key="activeScene.num"
                class="flex min-w-0 items-center justify-center gap-2.5 sm:gap-3"
            >
                <span
                    class="shrink-0 font-r10-body text-[11px] font-bold tracking-[0.18em] text-r10-orange uppercase"
                >
                    {{ stepLabel }}
                </span>
                <template v-if="!activeScene.intermission">
                    <span
                        class="h-4 w-px shrink-0 bg-white/25"
                        aria-hidden="true"
                    ></span>
                    <h2
                        class="min-w-0 truncate font-r10-display text-lg leading-tight font-bold tracking-[0.02em] text-white uppercase sm:text-xl"
                    >
                        {{ sceneLabel(activeScene.name) }}
                    </h2>
                </template>
            </div>
            <button
                type="button"
                class="col-start-3 shrink-0 cursor-pointer justify-self-end rounded-full border-2 border-white/30 bg-transparent px-4 py-2 font-r10-body text-xs font-bold tracking-[0.06em] text-white uppercase transition hover:border-r10-orange hover:text-r10-orange sm:px-5"
                @click="emit('close')"
            >
                Sulge vaade
            </button>
        </header>

        <div class="flex min-h-0 flex-1 flex-col md:flex-row">
            <!-- Scene stepper -->
            <aside
                class="flex max-h-[38vh] shrink-0 flex-col border-b border-white/15 bg-r10-navy md:max-h-none md:w-[280px] md:border-r md:border-b-0"
            >
                <div
                    class="shrink-0 px-5 pt-4 pb-2 font-r10-body text-[11px] font-bold tracking-[0.16em] text-r10-navy-300 uppercase"
                >
                    Stseenid · {{ sceneCount }}
                </div>
                <div ref="navRef" class="min-h-0 flex-1 overflow-y-auto pb-4">
                    <button
                        v-for="(scene, index) in scenes"
                        :key="index"
                        type="button"
                        :aria-current="index === active ? 'true' : undefined"
                        :class="[
                            'flex w-full cursor-pointer items-center gap-3 border-none px-5 py-2.5 text-left transition-colors',
                            index === active
                                ? 'bg-r10-orange/15'
                                : 'bg-transparent hover:bg-white/5',
                        ]"
                        @click="go(index)"
                    >
                        <!-- The interval keeps its place in the list, marked
                             rather than numbered: it is a step the tech stands
                             on, not one of the scenes they count. -->
                        <span
                            :class="[
                                'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 font-r10-display text-[13px] font-extrabold',
                                scene.intermission
                                    ? 'border-dashed'
                                    : 'border-solid',
                                index === active
                                    ? 'border-r10-orange bg-r10-orange text-r10-navy'
                                    : index < active
                                      ? 'border-r10-navy-300 bg-transparent text-r10-navy-200'
                                      : 'border-white/20 bg-transparent text-r10-navy-300',
                            ]"
                        >
                            <Diamond v-if="scene.intermission" :size="9" />
                            <template v-else>{{ scene.num }}</template>
                        </span>
                        <span
                            :class="[
                                'min-w-0 font-r10-body text-sm font-bold tracking-[0.02em]',
                                index === active
                                    ? 'text-white'
                                    : 'text-r10-navy-200',
                            ]"
                        >
                            {{
                                scene.intermission
                                    ? intermissionLabel(scene.intermission)
                                    : sceneLabel(scene.name)
                            }}
                        </span>
                    </button>
                </div>

                <!-- Wall clock: the tech calls cues against the running time. -->
                <div
                    :class="[
                        'shrink-0 border-t px-5 py-4 transition-colors',
                        overrunning
                            ? 'border-r10-orange bg-r10-orange'
                            : 'border-white/15',
                    ]"
                >
                    <div
                        :class="[
                            'font-r10-body text-[11px] font-bold tracking-[0.16em] uppercase',
                            clockLabelClass,
                        ]"
                    >
                        Kell
                    </div>
                    <time
                        :datetime="clockDateTime"
                        :class="[
                            'mt-1.5 block font-mono text-4xl leading-none font-bold tabular-nums transition-colors',
                            overrunning ? 'text-r10-navy' : 'text-white',
                        ]"
                    >
                        {{ clock }}
                    </time>

                    <!-- The slot the show is due to fill. Spelt out inside the
                         inverted panel as well, so the warning does not rest on
                         colour alone. -->
                    <div
                        v-if="schedule"
                        class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1"
                    >
                        <span
                            :class="[
                                'font-r10-body text-[11px] font-bold tracking-[0.16em] uppercase',
                                clockLabelClass,
                            ]"
                        >
                            Etendus
                        </span>
                        <span
                            :class="[
                                'font-mono text-sm font-bold tabular-nums',
                                overrunning
                                    ? 'text-r10-navy'
                                    : 'text-r10-navy-200',
                            ]"
                        >
                            {{ schedule.start
                            }}<template v-if="schedule.end"
                                >–{{ schedule.end }}</template
                            >
                        </span>
                        <span
                            v-if="schedule.overrunning"
                            class="rounded-full bg-r10-navy px-2 py-0.5 font-r10-body text-[11px] font-bold tracking-[0.16em] text-r10-orange uppercase"
                        >
                            Üle aja
                        </span>
                    </div>
                </div>
            </aside>

            <!-- Active scene -->
            <main
                v-if="activeScene"
                ref="mainRef"
                class="min-h-0 min-w-0 flex-1 overflow-y-auto px-4 py-5 sm:px-10 sm:py-6"
            >
                <div
                    :key="active"
                    class="mx-auto max-w-[820px] animate-[r10fade_0.28s_ease]"
                >
                    <!-- Nothing is played through the interval, so the step
                         says how long the house is out and no more. -->
                    <section
                        v-if="activeScene.intermission"
                        class="rounded-[14px] border-2 border-dashed border-r10-orange/60 bg-r10-navy px-4 py-10 text-center sm:px-5"
                    >
                        <div :class="cueLabelClass">Vaheaeg</div>
                        <div
                            class="mt-3 font-r10-display text-4xl leading-none font-bold tracking-[0.02em] text-white uppercase"
                        >
                            {{ activeScene.intermission }} min
                        </div>
                    </section>

                    <div v-else class="flex flex-col gap-6">
                        <section
                            class="rounded-[14px] border border-white/15 bg-r10-navy px-4 py-4 sm:px-5"
                        >
                            <div :class="cueLabelClass">Valgus</div>
                            <div :class="cueBodyClass">
                                <template v-if="activeScene.light">{{
                                    activeScene.light
                                }}</template>
                                <span v-else class="text-r10-navy-300">—</span>
                            </div>
                        </section>

                        <section
                            class="rounded-[14px] border border-white/15 bg-r10-navy px-4 py-4 sm:px-5"
                        >
                            <div :class="cueLabelClass">Heli</div>

                            <!-- One cue per row, in the order they are played:
                                 the player where the browser can read the
                                 sound, and always the name or link under it.
                                 Keyed on the row so switching scenes builds
                                 fresh players instead of re-sourcing playing
                                 ones. -->
                            <div
                                v-for="sound in activeScene.sounds"
                                :key="`${activeScene.num}-${sound.id}`"
                                class="mt-3"
                            >
                                <SceneAudio
                                    v-if="sound.audio"
                                    :src="sound.audio"
                                />

                                <a
                                    v-if="sound.file"
                                    :href="sound.file.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-1.5 block"
                                    :class="[cueBodyClass, cueLinkClass]"
                                >
                                    {{ sound.file.name }}
                                    <span class="text-r10-navy-200"
                                        >({{
                                            formatFileSize(sound.file.size)
                                        }})</span
                                    >
                                </a>
                                <a
                                    v-else
                                    :href="sound.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-1.5 block break-all"
                                    :class="[cueBodyClass, cueLinkClass]"
                                >
                                    {{ sound.url }}
                                </a>
                            </div>

                            <!-- How the cues are used, in the performer's own
                                 words. One description for the whole scene. -->
                            <div :class="cueBodyClass">
                                <template v-if="activeScene.sound">{{
                                    activeScene.sound
                                }}</template>
                                <span
                                    v-else-if="!activeScene.sounds.length"
                                    class="text-r10-navy-300"
                                    >—</span
                                >
                            </div>
                        </section>

                        <section
                            class="rounded-[14px] border border-white/15 bg-r10-navy px-4 py-4 sm:px-5"
                        >
                            <div :class="cueLabelClass">Märkmed</div>
                            <div :class="cueBodyClass">
                                <template v-if="activeScene.notes">{{
                                    activeScene.notes
                                }}</template>
                                <span v-else class="text-r10-navy-300">—</span>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>

        <footer
            class="flex shrink-0 items-center gap-2 border-t border-white/15 bg-r10-navy px-4 py-3 sm:gap-4 sm:px-5 sm:py-3.5"
        >
            <button
                type="button"
                :disabled="!hasPrevious"
                class="shrink-0 cursor-pointer rounded-full border-2 border-white/30 bg-transparent px-4 py-2.5 font-r10-body text-sm font-bold tracking-[0.04em] text-white uppercase transition hover:border-r10-orange hover:text-r10-orange disabled:pointer-events-none disabled:opacity-35 sm:px-6"
                @click="previous"
            >
                Eelmine
            </button>
            <span
                class="mx-auto text-center font-r10-body text-[11px] font-bold tracking-[0.1em] text-r10-navy-300 uppercase sm:text-xs"
            >
                {{ stepLabel }}
            </span>
            <button
                type="button"
                :disabled="!hasNext"
                class="shrink-0 cursor-pointer rounded-full border-none bg-r10-orange px-4 py-2.5 font-r10-body text-sm font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:bg-r10-orange-600 disabled:pointer-events-none disabled:opacity-35 sm:px-6"
                @click="next"
            >
                Järgmine
            </button>
        </footer>
    </div>
</template>
