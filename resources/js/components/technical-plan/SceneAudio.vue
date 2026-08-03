<script setup lang="ts">
import { Pause, Play, RotateCcw } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import WaveSurfer from 'wavesurfer.js';

const props = defineProps<{
    /** Direct URL to an audio file — see `playableAudio()`. */
    src: string;
}>();

const waveformRef = ref<HTMLElement | null>(null);

// The instance is not reactive state; wrapping it would make Vue walk the
// whole audio graph on every tick.
const wavesurfer = shallowRef<WaveSurfer | null>(null);

const ready = ref(false);
const failed = ref(false);
const playing = ref(false);
const currentTime = ref(0);
const duration = ref(0);

function formatTime(seconds: number): string {
    const whole = Math.max(Math.floor(seconds), 0);
    const minutes = Math.floor(whole / 60);

    return `${minutes}:${String(whole % 60).padStart(2, '0')}`;
}

function destroy(): void {
    wavesurfer.value?.destroy();
    wavesurfer.value = null;
}

function create(): void {
    destroy();

    if (!waveformRef.value) {
        return;
    }

    ready.value = false;
    failed.value = false;
    playing.value = false;
    currentTime.value = 0;
    duration.value = 0;

    const instance = WaveSurfer.create({
        container: waveformRef.value,
        url: props.src,
        height: 72,
        waveColor: '#8b9fc8',
        progressColor: '#ff7f50',
        cursorColor: '#ffffff',
        cursorWidth: 2,
        barWidth: 2,
        barGap: 1,
        barRadius: 2,
        // Scale peaks to the full height: a quietly mastered cue is just as
        // readable at a glance as a loud one.
        normalize: true,
    });

    instance.on('ready', () => {
        ready.value = true;
        duration.value = instance.getDuration();
    });
    instance.on('timeupdate', (time: number) => (currentTime.value = time));
    instance.on('play', () => (playing.value = true));
    instance.on('pause', () => (playing.value = false));
    instance.on('finish', () => (playing.value = false));
    // A linked file on another host may refuse to be read (CORS) or be gone;
    // the scene still lists it as a link, so the tech is not left stranded.
    instance.on('error', () => {
        failed.value = true;
        ready.value = false;
    });

    wavesurfer.value = instance;
}

function togglePlay(): void {
    wavesurfer.value?.playPause();
}

/** Back to the top of the cue, ready to fire again. */
function restart(): void {
    wavesurfer.value?.stop();
    currentTime.value = 0;
}

// The container renders unconditionally, so it exists by the time we mount.
onMounted(create);

watch(() => props.src, create, { flush: 'post' });

onBeforeUnmount(destroy);
</script>

<template>
    <div
        class="rounded-[12px] border border-white/15 bg-r10-navy-900 p-3 sm:p-4"
    >
        <!-- The waveform needs room the transport controls leave it: on a phone
             it drops to a line of its own instead of being squeezed to nothing. -->
        <div class="flex flex-wrap items-center gap-3 sm:flex-nowrap sm:gap-4">
            <button
                type="button"
                :disabled="!ready"
                :title="playing ? 'Peata' : 'Mängi'"
                :aria-label="playing ? 'Peata' : 'Mängi'"
                class="flex h-12 w-12 shrink-0 cursor-pointer items-center justify-center rounded-full border-none bg-r10-orange text-r10-navy transition hover:bg-r10-orange-600 disabled:pointer-events-none disabled:opacity-35"
                @click="togglePlay"
            >
                <Pause v-if="playing" class="h-5 w-5" />
                <Play v-else class="h-5 w-5" />
            </button>

            <div
                class="order-last w-full min-w-0 sm:order-none sm:w-auto sm:flex-1"
            >
                <!-- Wavesurfer draws into this element; it must stay in the
                     DOM for the lifetime of the instance. -->
                <div ref="waveformRef" class="w-full" />

                <div
                    v-if="!ready && !failed"
                    class="flex h-[72px] items-center gap-2.5"
                >
                    <span
                        class="h-2 w-2 shrink-0 rotate-45 animate-[r10spin_1s_linear_infinite] rounded-[1px] bg-r10-orange"
                    />
                    <span class="font-r10-body text-xs text-r10-navy-200">
                        Laen helilainet…
                    </span>
                </div>
            </div>

            <div
                v-if="ready"
                class="shrink-0 font-mono text-sm font-bold text-white tabular-nums"
            >
                {{ formatTime(currentTime) }}
                <span class="text-r10-navy-300">
                    / {{ formatTime(duration) }}
                </span>
            </div>

            <button
                v-if="ready"
                type="button"
                title="Algusesse"
                aria-label="Algusesse"
                class="ml-auto flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full border-2 border-white/30 bg-transparent text-white transition hover:border-r10-orange hover:text-r10-orange sm:ml-0"
                @click="restart"
            >
                <RotateCcw class="h-4 w-4" />
            </button>
        </div>

        <p
            v-if="failed"
            class="mt-3 mb-0 font-r10-body text-xs leading-normal text-r10-orange"
        >
            Heli ei õnnestunud laadida. Ava fail allolevalt lingilt.
        </p>
    </div>
</template>
