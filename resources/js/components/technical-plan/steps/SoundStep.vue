<script setup lang="ts">
import { usePlan } from '../planKey';
import R10Textarea from '../R10Textarea.vue';
import RadioPills from '../RadioPills.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();

const yesNo = [
    { value: 'no' as const, label: 'Ei' },
    { value: 'yes' as const, label: 'Jah' },
];
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 3 / 7 · Heliplaan"
            title="Heliplaan"
            lead="Mikrofonid ja instrumenti mängiv muusik."
        />

        <div class="flex flex-col gap-[26px]">
            <div>
                <div
                    class="mb-2.5 font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                >
                    Kas kasutad mikrofone?
                </div>
                <p class="-mt-1 mb-2.5 text-xs text-r10-grey-500">
                    Kui jah — kogus, paigutus ja kas töötav mikrofon või
                    rekvisiit.
                </p>
                <RadioPills v-model="plan.sound.micsMode" :options="yesNo" />
                <div v-if="plan.sound.micsMode === 'yes'" class="mt-4">
                    <R10Textarea
                        v-model="plan.sound.micsDetail"
                        hint="Kogus ja paigutus laval. N.B! Juhtmeta käsimikrofone saad kasutada maksimaalselt 1tk"
                        placeholder="Nt 2 käsimikrofoni, üks kummaski lava servas"
                        min-height="80px"
                    />
                </div>
            </div>

            <div class="border-t border-r10-grey-200 pt-[22px]">
                <div
                    class="mb-2.5 font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                >
                    Kasutad oma muusikut?
                </div>
                <p class="-mt-1 mb-2.5 text-xs text-r10-grey-500">
                    Instrument, PA vs akustiline, voolu- ja kaablivajadus, kes
                    toob.
                </p>
                <RadioPills
                    v-model="plan.sound.musicianMode"
                    :options="yesNo"
                />
                <div v-if="plan.sound.musicianMode === 'yes'" class="mt-4">
                    <R10Textarea
                        v-model="plan.sound.musicianDetail"
                        hint="Instrument ja kas ühendada helisüsteemi? Muusiku paigutus laval."
                        placeholder="Nt kitarr, palun ühendada helisüsteemi, muusik istub toolil lava aknapoolses ääres"
                        min-height="80px"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
