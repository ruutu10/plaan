<script setup lang="ts">
import { Link, Music, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import type {
    PlanFile,
    ReusableSound,
    Scene,
    SceneSound,
} from '@/types/technicalPlan';
import {
    acceptAttribute,
    discardSoundFile,
    extensionHint,
    fetchReusableSounds,
    reuseSound,
    uploadAttachment,
    validationError,
} from './attachments';
import { formatFileSize, nextSoundId, SOUND_PRESETS } from './plan';
import { usePlan, useWizardConfig } from './planKey';
import R10Dialog from './R10Dialog.vue';
import R10Dropzone from './R10Dropzone.vue';
import R10FileChip from './R10FileChip.vue';
import RadioPills from './RadioPills.vue';

/**
 * Adding a cue to a scene, in three steps: press the button, say where the
 * sound comes from, then describe how it is used.
 *
 * The third step writes to the scene's own description — the one field the plan
 * has ever had for this — rather than to the cue. A scene's sound is described
 * once, as a whole, whether it plays one file or four.
 */
const props = defineProps<{ sceneId: string }>();

const open = defineModel<boolean>('open', { required: true });

const plan = usePlan();
const config = useWizardConfig();

/**
 * The scene being added to, looked up on the injected plan rather than handed
 * in: the dialog writes to it, and the plan is where that write belongs.
 * Scene ids are unique within a plan, so the lookup is exact.
 */
const scene = computed<Scene>(
    () =>
        plan.scenes.find((candidate) => candidate.id === props.sceneId) ??
        plan.scenes[0],
);

/** The collection a scene's sound file is stored in, server-side. */
const SOUND_COLLECTION = 'sound';

type Source = 'upload' | 'url' | 'reuse';

const SOURCES: { value: Source; label: string; icon: Component }[] = [
    { value: 'upload', label: 'Laadi üles', icon: Upload },
    { value: 'url', label: 'Link', icon: Link },
    { value: 'reuse', label: 'Vali olemasolev', icon: Music },
];

const step = ref<'source' | 'description'>('source');
const source = ref<Source>('upload');
const url = ref('');
const urlError = ref('');

/** The cue just added, shown on the description step so its upload is visible. */
const added = ref<SceneSound | null>(null);

const soundAccept = computed(() => acceptAttribute(config.soundExtensions));

const soundExtensionHint = computed(() =>
    extensionHint(config.soundExtensions),
);

/** Start over each time the dialog opens — it never resumes a half-made cue. */
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    step.value = 'source';
    source.value = 'upload';
    url.value = '';
    urlError.value = '';
    added.value = null;
    loadedOthers.value = false;
});

/* ---- Step 2: where the sound comes from ------------------------------ */

/**
 * Put the cue on the scene and move on to describing it. The upload (or the
 * copy) carries on in the background — the chip on the next step reports how
 * it went, so a failure is never silent.
 */
function addSound(sound: Omit<SceneSound, 'id'>): SceneSound {
    scene.value.sounds.push({ ...sound, id: nextSoundId(scene.value.sounds) });

    // Read the pushed entry back out, so what is handed on is the reactive
    // proxy the scene holds rather than the plain object that went in.
    const entry = scene.value.sounds[scene.value.sounds.length - 1];

    added.value = entry;
    step.value = 'description';

    return entry;
}

async function onFile(files: FileList): Promise<void> {
    const file = files[0];

    if (!file) {
        return;
    }

    const error = validationError(file, config, config.soundExtensions);

    if (error) {
        addSound({
            url: '',
            file: {
                id: '',
                name: file.name,
                size: file.size,
                status: 'error',
                error,
            },
        });

        return;
    }

    const entry = addSound({
        url: '',
        file: { id: '', name: file.name, size: file.size, status: 'uploading' },
    });

    entry.file = await uploadAttachment(file, SOUND_COLLECTION);
}

function addLink(): void {
    const value = url.value.trim();

    if (!value) {
        urlError.value = 'Lisa helifaili link.';

        return;
    }

    addSound({ url: value, file: null });
}

/* ---- Step 2c: a sound the performer already has ---------------------- */

/**
 * The cues already on this plan. Offered without asking the server — the wizard
 * is holding them — and picked without copying: two scenes name one stored
 * file, which stays until the last of them lets it go.
 */
const ownSounds = computed(() => {
    const seen = new Set<string>();
    const rows: { file: PlanFile; scene: string }[] = [];

    plan.scenes.forEach((scene, index) => {
        scene.sounds.forEach((sound) => {
            const file = sound.file;

            if (file?.status !== 'ready' || !file.id || seen.has(file.id)) {
                return;
            }

            seen.add(file.id);
            rows.push({
                file,
                scene: scene.name.trim() || `Stseen ${index + 1}`,
            });
        });
    });

    return rows;
});

const otherSounds = ref<ReusableSound[]>([]);
const loadingOthers = ref(false);
const loadedOthers = ref(false);

const hasReusable = computed(
    () => ownSounds.value.length > 0 || otherSounds.value.length > 0,
);

// The performer's other plans are only worth fetching once the picker is asked
// for, and once per opening of the dialog.
watch([open, source], async ([isOpen, mode]) => {
    if (!isOpen || mode !== 'reuse' || loadedOthers.value || loadingOthers.value) {
        return;
    }

    loadingOthers.value = true;
    otherSounds.value = await fetchReusableSounds(plan.token);
    loadingOthers.value = false;
    loadedOthers.value = true;
});

function pickOwn(file: PlanFile): void {
    addSound({ url: '', file: { ...file } });
}

/**
 * A file from one of the performer's other plans is copied rather than shared:
 * this plan gets its own, so dropping the cue later never reaches back into the
 * plan it came from.
 */
async function pickOther(sound: ReusableSound): Promise<void> {
    const entry = addSound({
        url: '',
        file: {
            id: '',
            name: sound.name,
            size: sound.size,
            status: 'uploading',
        },
    });

    entry.file = await reuseSound(sound);
}

/* ---- Step 3: how the sound is used ----------------------------------- */

/** Undo the cue just added, without leaving the step describing it. */
async function removeAdded(): Promise<void> {
    const entry = added.value;

    if (!entry) {
        return;
    }

    const index = scene.value.sounds.indexOf(entry);

    if (index >= 0) {
        scene.value.sounds.splice(index, 1);
    }

    added.value = null;

    await discardSoundFile(plan, entry.file);
}

function appendSound(text: string): void {
    scene.value.sound = scene.value.sound.trim()
        ? `${scene.value.sound.trimEnd()}\n${text}`
        : text;
}

const fieldClass =
    'w-full rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-r10-body text-[13px] text-r10-ink outline-none focus:border-r10-orange';

const buttonClass =
    'cursor-pointer rounded-full border-2 px-5 py-2 font-r10-body text-xs font-bold tracking-[0.06em] uppercase transition';

const presetClass =
    'cursor-pointer rounded-full border border-r10-grey-200 bg-r10-grey-100 px-3 py-1 font-r10-body text-[11px] font-bold tracking-[0.03em] text-r10-navy transition hover:border-r10-orange hover:text-r10-orange';

const rowClass =
    'flex w-full cursor-pointer items-center gap-3 rounded-[10px] border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 text-left transition hover:border-r10-orange';
</script>

<template>
    <R10Dialog
        v-model:open="open"
        :title="step === 'source' ? 'Lisa heli' : 'Kirjelda heli kasutust'"
        :description="
            step === 'source'
                ? 'Kust see heli tuleb?'
                : 'Kirjeldus käib kogu stseeni heli kohta — nii ühe kui mitme faili puhul.'
        "
    >
        <!-- Step 2: the source -->
        <div v-if="step === 'source'" class="flex flex-col gap-4">
            <RadioPills v-model="source" compact :options="SOURCES" />

            <R10Dropzone
                v-if="source === 'upload'"
                compact
                label="Vali helifail"
                :hint="`Lubatud: ${soundExtensionHint}`"
                :accept="soundAccept"
                @files="onFile"
            />

            <template v-else-if="source === 'url'">
                <input
                    v-model="url"
                    type="url"
                    placeholder="Link helifailile (https://…)"
                    :class="fieldClass"
                    @keydown.enter.prevent="addLink"
                />
                <p v-if="urlError" class="text-xs text-r10-error">
                    {{ urlError }}
                </p>
                <div class="flex justify-end">
                    <button
                        type="button"
                        :class="[
                            buttonClass,
                            'border-r10-orange bg-r10-orange text-r10-navy hover:bg-r10-orange-600',
                        ]"
                        @click="addLink"
                    >
                        Lisa link
                    </button>
                </div>
            </template>

            <template v-else>
                <p v-if="loadingOthers" class="text-sm text-r10-grey-500">
                    Otsin varem lisatud helisid…
                </p>
                <p
                    v-else-if="!hasReusable"
                    class="text-sm text-r10-grey-500"
                >
                    Sul pole veel ühtegi üleslaaditud helifaili. Laadi esimene
                    üles või lisa link.
                </p>

                <div
                    v-else
                    class="flex max-h-[45vh] flex-col gap-4 overflow-y-auto"
                >
                    <div v-if="ownSounds.length" class="flex flex-col gap-1.5">
                        <div
                            class="font-r10-body text-[11px] font-bold tracking-[0.16em] text-r10-grey-500 uppercase"
                        >
                            Sellel plaanil
                        </div>
                        <button
                            v-for="row in ownSounds"
                            :key="row.file.id"
                            type="button"
                            :class="rowClass"
                            @click="pickOwn(row.file)"
                        >
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span
                                    class="truncate text-sm font-medium text-r10-ink"
                                >
                                    {{ row.file.name }}
                                </span>
                                <span class="text-xs text-r10-grey-500">
                                    {{ row.scene }}
                                </span>
                            </span>
                            <span class="shrink-0 text-xs text-r10-grey-500">
                                {{ formatFileSize(row.file.size) }}
                            </span>
                        </button>
                    </div>

                    <div v-if="otherSounds.length" class="flex flex-col gap-1.5">
                        <div
                            class="font-r10-body text-[11px] font-bold tracking-[0.16em] text-r10-grey-500 uppercase"
                        >
                            Sinu teistelt plaanidelt
                        </div>
                        <button
                            v-for="sound in otherSounds"
                            :key="sound.id"
                            type="button"
                            :class="rowClass"
                            @click="pickOther(sound)"
                        >
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span
                                    class="truncate text-sm font-medium text-r10-ink"
                                >
                                    {{ sound.name }}
                                </span>
                                <span class="truncate text-xs text-r10-grey-500">
                                    {{ sound.planLabel
                                    }}<template v-if="sound.performanceDate">
                                        · {{ sound.performanceDate }}</template
                                    >
                                </span>
                            </span>
                            <span class="shrink-0 text-xs text-r10-grey-500">
                                {{ formatFileSize(sound.size) }}
                            </span>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Step 3: the description -->
        <div v-else class="flex flex-col gap-3">
            <R10FileChip
                v-if="added?.file"
                :file="added.file"
                open-label="Ava uues aknas"
                @remove="removeAdded"
            />
            <p v-else-if="added" class="truncate text-sm text-r10-grey-500">
                {{ added.url }}
            </p>

            <textarea
                v-model="scene.sound"
                placeholder="Heli kasutuse kirjeldus, nt „sissetulekumuusika kuni esinejad on kohal, väljaminekul sama lugu uuesti“"
                class="min-h-[96px] w-full resize-y rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-r10-body text-sm leading-normal text-r10-ink outline-none focus:border-r10-orange"
            ></textarea>

            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="preset in SOUND_PRESETS"
                    :key="preset"
                    type="button"
                    :class="presetClass"
                    @click="appendSound(preset)"
                >
                    + {{ preset }}
                </button>
            </div>
        </div>

        <template #actions>
            <button
                v-if="step === 'source'"
                type="button"
                :class="[
                    buttonClass,
                    'border-r10-grey-200 bg-white text-r10-grey-700 hover:border-r10-navy-300',
                ]"
                @click="open = false"
            >
                Loobu
            </button>
            <button
                v-else
                type="button"
                :class="[
                    buttonClass,
                    'border-r10-orange bg-r10-orange text-r10-navy hover:bg-r10-orange-600',
                ]"
                @click="open = false"
            >
                Valmis
            </button>
        </template>
    </R10Dialog>
</template>
