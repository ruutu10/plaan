<script setup lang="ts">
import { uid } from '../plan';
import { usePlan } from '../planKey';
import R10Textarea from '../R10Textarea.vue';
import RadioPills from '../RadioPills.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();

function addEquip(): void {
    plan.equipment.items.push({ id: uid(), name: '', use: '' });
}

function removeEquip(index: number): void {
    plan.equipment.items.splice(index, 1);
}

const smokeOptions = [
    { value: 'no' as const, label: 'Ei' },
    { value: 'yes' as const, label: 'Jah' },
];

const suggestOptions = [
    { value: 'no' as const, label: 'Ei' },
    { value: 'yes' as const, label: 'Jah' },
];
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 5 / 7 · Tehnilised erivahendid"
            title="Erivahendid"
            lead="Valikuline — kui etendus vajab erivahendeid nagu arvuti, tahvel, suitsumasin, projektor või video."
        />

        <div class="flex flex-col gap-3.5">
            <div
                v-for="(item, index) in plan.equipment.items"
                :key="item.id"
                class="grid grid-cols-1 items-end gap-3.5 rounded-[14px] border border-r10-grey-200 bg-white p-4 sm:grid-cols-[1fr_1.4fr_auto]"
            >
                <label class="flex flex-col gap-1.5">
                    <span
                        class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                    >
                        Nimetus
                    </span>
                    <input
                        v-model="item.name"
                        type="text"
                        placeholder="Nt suitsumasin"
                        class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-r10-body text-sm text-r10-ink outline-none focus:border-r10-orange"
                    />
                </label>
                <label class="flex flex-col gap-1.5">
                    <span
                        class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                    >
                        Kasutus / piirangud
                    </span>
                    <input
                        v-model="item.use"
                        type="text"
                        placeholder="Nt kerge haze lavaletuleku ajal"
                        class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-3.5 py-2.5 font-r10-body text-sm text-r10-ink outline-none focus:border-r10-orange"
                    />
                </label>
                <button
                    type="button"
                    title="Eemalda"
                    class="h-10 w-10 shrink-0 cursor-pointer rounded-lg border-2 border-r10-grey-200 bg-white text-base leading-none text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
                    @click="removeEquip(index)"
                >
                    ✕
                </button>
            </div>

            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-2.5 self-start rounded-full border-2 border-dashed border-r10-navy-300 bg-white px-5 py-2.5 font-r10-body text-[13px] font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:border-r10-orange hover:text-r10-orange"
                @click="addEquip"
            >
                <span class="text-lg leading-none">+</span> Lisa erivahend
            </button>
        </div>

        <div
            class="mt-[30px] flex flex-col gap-[26px] border-t border-r10-grey-200 pt-6"
        >
            <div>
                <div
                    class="mb-1.5 font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                >
                    Kas tehnik tohib kasutada suitsuefekte?
                </div>
                <p class="mt-0 mb-3 text-[13px] text-r10-grey-500">
                    N.B! Improkeskuse ruumides suitsuefekte kasutada ei saa, see valik kehtib ainult etendustel, mis toimuvad väljaspool improkeskust.
                </p>
                <RadioPills
                    v-model="plan.equipment.smoke"
                    :options="smokeOptions"
                />
            </div>

            <div class="border-t border-r10-grey-200 pt-[22px]">
                <div
                    class="mb-1.5 font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                >
                    Kas tehnik tohib teha omapoolseid stseeni mõjutavaid
                    pakkumisi?
                </div>
                <p class="mt-0 mb-3 text-[13px] text-r10-grey-500">
                    Nt panna taustale mängima muusika või suunata spotlight
                    ühele näitlejale. Kirjuta „ei“, kui tahad, et kõik läheks
                    täpselt sinu koostatud plaani järgi.
                </p>
                <RadioPills
                    v-model="plan.equipment.suggestions"
                    :options="suggestOptions"
                />
                <div class="mt-4">
                    <R10Textarea
                        v-model="plan.equipment.suggestNote"
                        hint="Täpsustus (valikuline)"
                        placeholder="Nt väikesed pakkumised on teretulnud…"
                        min-height="80px"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
