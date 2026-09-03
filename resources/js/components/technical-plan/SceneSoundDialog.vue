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
    extensionHint,
    fetchReusableSounds,
    reuseSound,
    uploadAttachment,
    validationError,
} from './attachments';
import { formatFileSize, nextSoundId, soundLinkError } from './plan';
import { usePlan, useWizardConfig } from './planKey';
import R10Dialog from './R10Dialog.vue';
import R10Dropzone from './R10Dropzone.vue';
import RadioPills from './RadioPills.vue';

/**
 * Adding a cue to a scene: pick where the sound comes from, give it, done.
 *
 * The cue is not described here. A scene's sound is described once, as a whole,
 * in the scene's own field — whether it plays one file or four — so the dialog
 * closes on the cue landing and leaves the writing to the card behind it.
 */
const props = defineProps<{ sceneId: string }>();

const open = defineModel<boolean>('open', { required: true });

const plan = usePlan();
const config = useWizardConfig();

/**
 * The scene being added to, looked up on the injected plan rather than handed
 * in: the dialog writes to it, and the plan is where that write belongs.
 * Scene ids are unique within a plan, so the lookup is exact.
 *
 * Left `undefined` when nothing matches. Standing in the first scene would be
 * the one wrong answer available — a cue quietly landing on somebody else's
 * scene — so a dialog that cannot find its scene does nothing at all.
 */
const scene = computed<Scene | undefined>(() =>
    plan.scenes.find((candidate) => candidate.id === props.sceneId),
);

/** The collection a scene's sound file is stored in, server-side. */
const SOUND_COLLECTION = 'sound';

type Source = 'upload' | 'url' | 'reuse';

const SOURCES: { value: Source; label: string; icon: Component }[] = [
    { value: 'upload', label: 'Laadi üles', icon: Upload },
    { value: 'url', label: 'Link', icon: Link },
    { value: 'reuse', label: 'Vali olemasolev', icon: Music },
];

const source = ref<Source>('upload');
const url = ref('');
const urlError = ref('');

const soundAccept = computed(() => acceptAttribute(config.soundExtensions));

const soundExtensionHint = computed(() =>
    extensionHint(config.soundExtensions),
);

/** Start over each time the dialog opens — it never resumes a half-made cue. */
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    source.value = 'upload';
    url.value = '';
    urlError.value = '';
    loadedOthers.value = false;
});

/* ---- Where the sound comes from -------------------------------------- */

/**
 * Put the cue on the scene and close. An upload (or a copy) carries on in the
 * background — the row it just added to the scene card reports how it went, so
 * a failure is never silent even though the dialog has gone.
 */
function addSound(sound: Omit<SceneSound, 'id'>): SceneSound | null {
    const target = scene.value;

    if (!target) {
        open.value = false;

        return null;
    }

    target.sounds.push({ ...sound, id: nextSoundId(target.sounds) });

    // Read the pushed entry back out, so what is handed on is the reactive
    // proxy the scene holds rather than the plain object that went in.
    const entry = target.sounds[target.sounds.length - 1];

    open.value = false;

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

    if (entry) {
        entry.file = await uploadAttachment(file, SOUND_COLLECTION);
    }
}

/**
 * Take the typed link, if it is one. A link that will not do keeps the dialog
 * open with the reason under the field — the performer is standing right at
 * the input that needs fixing, which is the only place the message helps.
 */
function addLink(): void {
    const value = url.value.trim();

    urlError.value = soundLinkError(value, config) ?? '';

    if (urlError.value !== '') {
        return;
    }

    addSound({ url: value, file: null });
}

// Typing is the performer answering the complaint; the message goes as soon as
// they do, rather than sitting there until they press the button again.
watch(url, () => {
    urlError.value = '';
});

/* ---- A sound the performer already has ------------------------------- */

/**
 * What this scene already plays, ready to be recognised again.
 *
 * A sound the scene has is not worth offering it a second time: picking it
 * would either name one stored file twice on the same scene, or — from another
 * plan — copy in a file the scene is already playing. Other scenes of this plan
 * are a different matter, and so are the performer's other plans; the exclusion
 * is only ever about *this* scene.
 *
 * Handles identify a file within this plan. A copy taken from another plan is
 * given a fresh one, so nothing links it back to its source and the file's name
 * and size have to stand in for identity there.
 */
const onThisScene = computed(() => {
    const handles = new Set<string>();
    const files = new Set<string>();

    (scene.value?.sounds ?? []).forEach((sound) => {
        if (!sound.file?.id) {
            return;
        }

        handles.add(sound.file.id);
        files.add(`${sound.file.name}:${sound.file.size}`);
    });

    return { handles, files };
});

function isOnThisScene(id: string, name: string, size: number): boolean {
    return (
        onThisScene.value.handles.has(id) ||
        onThisScene.value.files.has(`${name}:${size}`)
    );
}

/**
 * The cues already on this plan, less the ones this scene plays. Offered
 * without asking the server — the wizard is holding them — and picked without
 * copying: two scenes name one stored file, which stays until the last of them
 * lets it go.
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

            if (isOnThisScene(file.id, file.name, file.size)) {
                return;
            }

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

/** The other plans' sounds this scene has not already taken a copy of. */
const offeredOtherSounds = computed(() =>
    otherSounds.value.filter(
        (sound) => !isOnThisScene(sound.id, sound.name, sound.size),
    ),
);

const hasReusable = computed(
    () => ownSounds.value.length > 0 || offeredOtherSounds.value.length > 0,
);

/**
 * Whether there is nothing to offer because this scene is already playing it
 * all — a different thing to tell the performer than having no sounds at all,
 * and answered by what the scene holds rather than by re-counting the plan.
 */
const allAlreadyOnScene = computed(
    () => !hasReusable.value && onThisScene.value.handles.size > 0,
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

    if (entry) {
        entry.file = await reuseSound(sound);
    }
}

const fieldClass =
    'w-full rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-r10-body text-[13px] text-r10-ink outline-none focus:border-r10-orange';

const buttonClass =
    'cursor-pointer rounded-full border-2 px-5 py-2 font-r10-body text-xs font-bold tracking-[0.06em] uppercase transition';

const rowClass =
    'flex w-full cursor-pointer items-center gap-3 rounded-[10px] border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 text-left transition hover:border-r10-orange';
</script>

<template>
    <R10Dialog
        v-model:open="open"
        title="Lisa heli"
        description="Kust see heli tuleb? Laadi helifail siia üles või lisa Spotify/Youtube vms link, kust seda mängida saab."
    >
        <div class="flex flex-col gap-4">
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
                        Salvesta
                    </button>
                </div>
            </template>

            <template v-else>
                <p v-if="loadingOthers" class="text-sm text-r10-grey-500">
                    Otsin varem lisatud helisid…
                </p>
                <p
                    v-else-if="allAlreadyOnScene"
                    class="text-sm text-r10-grey-500"
                >
                    Kõik su üleslaaditud helifailid on juba selles stseenis.
                    Laadi uus üles või lisa link.
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

                    <div v-if="offeredOtherSounds.length" class="flex flex-col gap-1.5">
                        <div
                            class="font-r10-body text-[11px] font-bold tracking-[0.16em] text-r10-grey-500 uppercase"
                        >
                            Sinu teistelt plaanidelt
                        </div>
                        <button
                            v-for="sound in offeredOtherSounds"
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

    </R10Dialog>
</template>
