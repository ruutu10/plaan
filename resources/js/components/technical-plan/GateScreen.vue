<script setup lang="ts">
import { ref } from 'vue';
import type { LookupResult } from '@/types/technicalPlan';
import Diamond from './Diamond.vue';
import R10Button from './R10Button.vue';

defineProps<{
    lookupError: string;
    lookupResults: LookupResult[];
    lookupBusy: boolean;
}>();

const emit = defineEmits<{
    'start-blank': [];
    'run-lookup': [email: string];
    'open-submission': [token: string];
}>();

const lookupEmail = ref('');
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">

        <h1
            class="m-0 font-r10-display text-[34px] leading-[1.05] font-bold tracking-[0.02em] text-r10-ink uppercase"
        >
            Tehnikaplaani esitamine
        </h1>
        <p
            class="mt-2.5 mb-[30px] font-r10-body text-[17px] leading-relaxed text-r10-ink"
        >
            Selle ankeedi kaudu saad tehnilisele tiimile saata eelinfo oma etenduse
            valgus- ja helisoovide kohta.
</p>
 <p
            class="mt-2.5 mb-[30px] font-r10-body text-[17px] leading-relaxed font-light text-r10-grey-700"
        >
        N.B! Tehnikaplaan on vaja esitada iga etenduse kohta uuesti, isegi kui tegemist on sama formaadi kordusega. Kordusetenduse puhul vali <span class="font-bold">kasuta varem esitatud plaani</span> - nii saad aluseks eeltäidetud plaani, ja saad muuta ainult erisused (nt mängude järjekord).
        </p>

        <div class="grid grid-cols-1 items-start gap-[22px] sm:grid-cols-2">
            <div
                class="flex flex-col rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
            >
                <Diamond :size="14" class="mb-4" />
                <div
                    class="mb-2 font-r10-display text-[19px] font-bold tracking-[0.03em] text-r10-ink uppercase"
                >
                    Uus plaan
                </div>
                <p class="m-0 mb-5 text-sm leading-relaxed text-r10-grey-700">
                    Kui sa pole varem selle etenduse kohta tehnikaplaani saatnud, alusta siit, tühja ankeediga.
                </p>
                <R10Button
                    variant="primary"
                    size="lg"
                    @click="emit('start-blank')"
                >
                    Alusta uut plaani
                </R10Button>
            </div>

            <div
                class="rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
            >
                <span
                    class="mb-4 inline-block h-3.5 w-3.5 rotate-45 rounded-[2px] bg-r10-navy"
                />
                <div
                    class="mb-2 font-r10-display text-[19px] font-bold tracking-[0.03em] text-r10-ink uppercase"
                >
                   Kasuta varem esitatud plaani
                </div>
                <p
                    class="m-0 mb-4 max-w-[66ch] text-sm leading-relaxed text-r10-grey-700"
                >
                    Saatsid eelnevalt juba tehnikaplaani, ja soovid seda väikeste muudatustega kasutada järgmisel etendusel?
                    Siin saad eeltäita uue plaani varem esitatud plaani põhjal.


                    Sisesta e-post, millega oled varem plaane esitanud. Näitame
                    kõik selle aadressiga saadetud plaanid — ava mõni neist uue
                    plaani põhjaks.
                </p>
                <div class="flex flex-wrap items-end gap-2.5">
                    <label
                        class="flex min-w-0 flex-1 basis-[260px] flex-col gap-1.5"
                    >
                        <span
                            class="font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-ink uppercase"
                        >
                            E-post
                        </span>
                        <input
                            v-model="lookupEmail"
                            type="email"
                            placeholder="ando@ruutu10.ee"
                            class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange"
                        />
                    </label>
                    <R10Button
                        variant="outline"
                        size="lg"
                        :disabled="lookupBusy"
                        @click="emit('run-lookup', lookupEmail)"
                    >
                        Otsi plaane
                    </R10Button>
                </div>

                <div v-if="lookupError" class="mt-3 flex items-start gap-2">
                    <span
                        class="mt-[5px] h-2 w-2 shrink-0 rotate-45 rounded-[1px] bg-r10-error"
                    />
                    <span class="text-[13px] leading-normal text-r10-error">{{
                        lookupError
                    }}</span>
                </div>

                <div
                    v-if="lookupResults.length"
                    class="mt-4 flex flex-col gap-2"
                >
                    <button
                        v-for="result in lookupResults"
                        :key="result.token"
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-3.5 rounded-[10px] border border-r10-grey-200 bg-r10-grey-100 px-4 py-3 text-left transition hover:border-r10-orange hover:bg-r10-orange-100"
                        @click="emit('open-submission', result.token)"
                    >
                        <Diamond :size="9" />
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span
                                class="overflow-hidden font-r10-body text-[15px] font-bold text-ellipsis whitespace-nowrap text-r10-ink"
                            >
                                {{ result.title }}
                            </span>
                            <span class="text-xs text-r10-grey-500">{{
                                result.sub
                            }}</span>
                        </span>
                        <span
                            class="shrink-0 font-r10-body text-[11px] font-bold tracking-[0.06em] text-r10-navy uppercase"
                        >
                            Ava →
                        </span>
                    </button>
                </div>
            </div>


        </div>
    </section>
</template>
