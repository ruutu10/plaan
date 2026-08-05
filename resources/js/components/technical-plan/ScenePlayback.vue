<script setup lang="ts">
import { useEventListener, useNow } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { hideFeedbackWidget, showFeedbackWidget } from '@/lib/sentry';
import Diamond from './Diamond.vue';
import { formatFileSize, playableAudio } from './plan';
import { usePlan } from './planKey';
import { normaliseScenes } from './presentPlan';
import SceneAudio from './SceneAudio.vue';

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
    normaliseScenes(plan).map((scene, index) => ({
        ...scene,
        // Null for sound that only exists behind a link no player can read.
        audio: playableAudio(plan.scenes[index]),
    })),
);

const active = ref(0);

const activeScene = computed(() => scenes.value[active.value] ?? null);

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

const clock = computed(() =>
    now.value.toLocaleTimeString('et-EE', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }),
);

const clockDateTime = computed(() => now.value.toISOString());

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
        <header
            class="flex shrink-0 items-center gap-3 border-b border-white/15 bg-r10-navy px-4 py-3.5 sm:gap-4 sm:px-5"
        >
            <div class="flex min-w-0 items-center gap-2.5">
                <Diamond :size="11" />
                <span
                    class="font-r10-body text-[11px] font-bold tracking-[0.18em] text-r10-orange uppercase"
                >
                    Tehniku vaade
                </span>
            </div>
            <span
                class="hidden min-w-0 truncate font-r10-display text-[15px] font-semibold tracking-[0.03em] text-white uppercase sm:block"
            >
                {{ plan.meta.formatName || 'Nimeta etendus' }}
            </span>
            <button
                type="button"
                class="ml-auto shrink-0 cursor-pointer rounded-full border-2 border-white/30 bg-transparent px-4 py-2 font-r10-body text-xs font-bold tracking-[0.06em] text-white uppercase transition hover:border-r10-orange hover:text-r10-orange sm:px-5"
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
                    Stseenid · {{ scenes.length }}
                </div>
                <div ref="navRef" class="min-h-0 flex-1 overflow-y-auto pb-4">
                    <button
                        v-for="(scene, index) in scenes"
                        :key="scene.num"
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
                        <span
                            :class="[
                                'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 font-r10-display text-[13px] font-extrabold',
                                index === active
                                    ? 'border-r10-orange bg-r10-orange text-r10-navy'
                                    : index < active
                                      ? 'border-r10-navy-300 bg-transparent text-r10-navy-200'
                                      : 'border-white/20 bg-transparent text-r10-navy-300',
                            ]"
                        >
                            {{ scene.num }}
                        </span>
                        <span
                            :class="[
                                'min-w-0 font-r10-body text-sm font-bold tracking-[0.02em]',
                                index === active
                                    ? 'text-white'
                                    : 'text-r10-navy-200',
                            ]"
                        >
                            {{ sceneLabel(scene.name) }}
                        </span>
                    </button>
                </div>

                <!-- Wall clock: the tech calls cues against the running time. -->
                <div class="shrink-0 border-t border-white/15 px-5 py-4">
                    <div
                        class="font-r10-body text-[11px] font-bold tracking-[0.16em] text-r10-navy-300 uppercase"
                    >
                        Kell
                    </div>
                    <time
                        :datetime="clockDateTime"
                        class="mt-1.5 block font-mono text-4xl leading-none font-bold text-white tabular-nums"
                    >
                        {{ clock }}
                    </time>
                </div>
            </aside>

            <!-- Active scene -->
            <main
                v-if="activeScene"
                ref="mainRef"
                class="min-h-0 min-w-0 flex-1 overflow-y-auto px-4 py-6 sm:px-10 sm:py-7"
            >
                <div
                    :key="activeScene.num"
                    class="mx-auto max-w-[820px] animate-[r10fade_0.28s_ease]"
                >
                    <div
                        class="font-r10-body text-xs font-bold tracking-[0.18em] text-r10-navy-300 uppercase"
                    >
                        Stseen {{ activeScene.num }} / {{ scenes.length }}
                    </div>
                    <h2
                        class="mt-2 font-r10-display text-3xl leading-[1.1] font-bold tracking-[0.02em] break-words text-white uppercase sm:text-4xl"
                    >
                        {{ sceneLabel(activeScene.name) }}
                    </h2>

                    <div class="mt-8 flex flex-col gap-6">
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

                            <!-- Keyed so switching scenes builds a fresh
                                 player instead of re-sourcing a playing one.
                                 The file itself is named by the link below. -->
                            <SceneAudio
                                v-if="activeScene.audio"
                                :key="activeScene.audio"
                                class="mt-3"
                                :src="activeScene.audio"
                            />

                            <div :class="cueBodyClass">
                                <a
                                    v-if="activeScene.soundFile"
                                    :href="activeScene.soundFile.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block"
                                    :class="cueLinkClass"
                                >
                                    {{ activeScene.soundFile.name }}
                                    <span class="text-r10-navy-200"
                                        >({{
                                            formatFileSize(
                                                activeScene.soundFile.size,
                                            )
                                        }})</span
                                    >
                                </a>
                                <a
                                    v-if="activeScene.soundUrl"
                                    :href="activeScene.soundUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block break-all"
                                    :class="cueLinkClass"
                                >
                                    {{ activeScene.soundUrl }}
                                </a>
                                <template v-if="activeScene.sound">{{
                                    activeScene.sound
                                }}</template>
                                <span
                                    v-else-if="
                                        !activeScene.soundFile &&
                                        !activeScene.soundUrl
                                    "
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
                Stseen {{ active + 1 }} / {{ scenes.length }}
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
