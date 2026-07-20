<script setup lang="ts">
import { ref } from 'vue';
import type { Scene } from '@/types/technicalPlan';
import Diamond from '../Diamond.vue';
import { blankScene, uid } from '../plan';
import { usePlan } from '../planKey';
import R10Input from '../R10Input.vue';
import R10Textarea from '../R10Textarea.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();

const dragId = ref<string | null>(null);

const LIGHT_PRESETS = [
    'kiire blackout',
    'üldvalgus',
    'spot valgus lava keskel',
    'väga hämar sinine valgus',
    'fadeout 1s',
];

const SOUND_PRESETS = [
    'ruutu10 tunnus 3s',
    'ruutu10 tunnus 15s',
    'film noare (vabal valikul)',
    'shakespeare (vabal valikul)'
];


function appendLight(scene: Scene, text: string): void {
    scene.light = scene.light.trim() ? `${scene.light.trimEnd()}\n${text}` : text;
}

function appendSound(scene: Scene, text: string): void {
    scene.sound = scene.sound.trim() ? `${scene.sound.trimEnd()}\n${text}` : text;
}


function addScene(): void {
    plan.scenes.forEach((s) => (s.collapsed = true));
    plan.scenes.push(blankScene());
}

function toggle(scene: Scene): void {
    scene.collapsed = !scene.collapsed;
}

function duplicate(index: number): void {
    const copy: Scene = {
        ...plan.scenes[index],
        id: uid(),
        collapsed: false,
    };
    plan.scenes.forEach((s) => (s.collapsed = true));
    plan.scenes.splice(index + 1, 0, copy);
}

function remove(index: number): void {
    if (plan.scenes.length > 1) {
        plan.scenes.splice(index, 1);
    }
}

function onDrop(targetId: string): void {
    const from = plan.scenes.findIndex((s) => s.id === dragId.value);
    const to = plan.scenes.findIndex((s) => s.id === targetId);

    if (from < 0 || to < 0 || from === to) {
        dragId.value = null;

        return;
    }

    const [moved] = plan.scenes.splice(from, 1);
    plan.scenes.splice(to, 0, moved);
    dragId.value = null;
}
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 4 / 7 · Stseenid"
            title="Stseenid"
            lead="Stseen on etenduse loogiline või tehniline osa, kus kasutatud heli- või valguslahendus muutub. Kirjelda siin kõik erinevad valgus- ja helilahendused, mida sinu etendus vajab."
        />

        <div class="flex flex-col gap-[18px]">
            <div
                v-for="(scene, index) in plan.scenes"
                :key="scene.id"
                class="overflow-hidden rounded-[14px] border border-r10-grey-200 bg-r10-grey-100"
                @dragover.prevent
                @drop.prevent="onDrop(scene.id)"
            >
                <div
                    class="flex items-center gap-3 bg-r10-navy px-4 py-3 text-white"
                >
                    <span
                        draggable="true"
                        title="Lohista ümberjärjestamiseks"
                        class="flex shrink-0 cursor-grab text-r10-navy-300"
                        @dragstart="dragId = scene.id"
                    >
                        <svg
                            width="12"
                            height="18"
                            viewBox="0 0 12 18"
                            fill="currentColor"
                        >
                            <circle cx="3" cy="3" r="1.5" />
                            <circle cx="9" cy="3" r="1.5" />
                            <circle cx="3" cy="9" r="1.5" />
                            <circle cx="9" cy="9" r="1.5" />
                            <circle cx="3" cy="15" r="1.5" />
                            <circle cx="9" cy="15" r="1.5" />
                        </svg>
                    </span>
                    <button
                        type="button"
                        title="Ava/sulge"
                        class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 border-none bg-transparent p-0 text-left text-white"
                        @click="toggle(scene)"
                    >
                        <svg
                            width="12"
                            height="12"
                            viewBox="0 0 12 12"
                            fill="none"
                            class="shrink-0 transition-transform"
                            :style="{
                                transform: scene.collapsed
                                    ? 'rotate(-90deg)'
                                    : 'none',
                            }"
                        >
                            <path
                                d="M2.5 4.5L6 8l3.5-3.5"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        <Diamond :size="9" />
                        <span
                            class="shrink-0 font-r10-display text-sm font-semibold tracking-[0.03em] uppercase"
                        >
                            Stseen {{ index + 1 }}
                        </span>
                        <span
                            v-if="scene.collapsed"
                            class="min-w-0 overflow-hidden text-[13px] text-ellipsis whitespace-nowrap text-r10-navy-200"
                        >
                            {{
                                scene.name?.trim()
                                    ? scene.name
                                    : 'Nimeta stseen'
                            }}
                        </span>
                    </button>
                    <div class="ml-auto flex shrink-0 gap-2">
                        <button
                            type="button"
                            class="cursor-pointer rounded-full border border-white/20 bg-white/10 px-3 py-1.5 font-r10-body text-[11px] font-bold tracking-[0.06em] text-white uppercase"
                            @click="duplicate(index)"
                        >
                            Kopeeri
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-full border border-white/20 bg-white/10 px-3 py-1.5 font-r10-body text-[11px] font-bold tracking-[0.06em] text-white uppercase',
                                plan.scenes.length > 1
                                    ? 'cursor-pointer'
                                    : 'pointer-events-none opacity-35',
                            ]"
                            @click="remove(index)"
                        >
                            Kustuta
                        </button>
                    </div>
                </div>

                <div
                    v-if="!scene.collapsed"
                    class="flex flex-col gap-4 bg-white p-4"
                >
                    <R10Input
                        v-model="scene.name"
                        label="Nimi"
                        placeholder="Nt 'lavale tulek' või 'järgmise mängu tutvustus'"
                    />
                    <div class="grid grid-cols-1 gap-4">
                        <div class="flex flex-col gap-2">
                            <R10Textarea
                                v-model="scene.light"
                                label="Valgus"
                                placeholder="Valguse soovid, nt 'Spot lava keskele' või 'punane hämar valgus üle kogu lava'"
                            />
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="preset in LIGHT_PRESETS"
                                    :key="preset"
                                    type="button"
                                    :class="[
                                        'rounded-full border border-r10-grey-200 bg-r10-grey-100 px-3 py-1 font-r10-body text-[11px] font-bold tracking-[0.03em] text-r10-navy transition cursor-pointer hover:border-r10-orange hover:text-r10-orange',
                                    ]"
                                    @click="appendLight(scene, preset)"
                                >
                                    + {{ preset }}
                                </button>
                            </div>
                        </div>
                        <label class="flex flex-col gap-1.5">
                            <span
                                class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                            >
                                Heli
                            </span>
                            <input
                                v-model="scene.soundUrl"
                                type="url"
                                placeholder="Link helifailile (https://…)"
                                class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-r10-body text-[13px] text-r10-ink outline-none focus:border-r10-orange"
                            />
                            <textarea
                                v-model="scene.sound"
                                placeholder="Heli kasutuse kirjeldus, nt „alusta 10. sekundist, pane mängima siis kui esinejad tarduvad“"
                                class="min-h-[56px] w-full resize-y rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-r10-body text-sm leading-normal text-r10-ink outline-none focus:border-r10-orange"
                            ></textarea>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="preset in SOUND_PRESETS"
                                    :key="preset"
                                    type="button"
                                    :class="[
                                        'rounded-full border border-r10-grey-200 bg-r10-grey-100 px-3 py-1 font-r10-body text-[11px] font-bold tracking-[0.03em] text-r10-navy transition cursor-pointer hover:border-r10-orange hover:text-r10-orange',
                                    ]"
                                    @click="appendSound(scene, preset)"
                                >
                                    + {{ preset }}
                                </button>
                            </div>
                        </label>
                        <R10Textarea
                            v-model="scene.notes"
                            label="Märkmed"
                            placeholder="Muu oluline…"
                        />
                    </div>
                </div>
            </div>
        </div>

        <button
            type="button"
            class="mt-[18px] inline-flex cursor-pointer items-center gap-2.5 rounded-full border-2 border-dashed border-r10-navy-300 bg-white px-[22px] py-2.5 font-r10-body text-[13px] font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:border-r10-orange hover:text-r10-orange"
            @click="addScene"
        >
            <span class="text-lg leading-none">+</span> Lisa stseen
        </button>
    </section>
</template>
