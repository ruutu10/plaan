<script setup lang="ts">
import StepHeader from '../StepHeader.vue';

const points = [
    'Tehnik istub tehnikalauas, publiku selja taga',
    'Esinejatele on lavalt nähtav suur taimer-kell. Kella käivitab tehnik etenduse alguses (kui õhtujuht on esinejad lavale kutsunud). Kell tiksub maha, nullini.',
    'Kui stseenide tabelis pole teisiti kirjeldatud, lõpetab etenduse tehnik valguse kustutamisega (fadeout või kiire blackout). Head lõpukohta hakatakse otsima, kui aega on jäänud alla 3 minuti.',
    'Etenduse lõpetav blackout kestab 5–10 sekundit — tüüpiliselt hakatakse plaksutama.',
    'Saal avatakse publikule 30 min enne algust. Tehnilised läbimängud valguse ja heliga on 60 min enne algust — tungiv soovitus kogu grupiga läbi proovida.',
    'Kui pole märgitud teisiti, võib valgus etenduse ajal muutuda — valgustaja võib pimendada kasutamata lavaosa või kasutada värvilist valgust meeleolu rõhutamiseks.',
    'Kui pole märgitud teisiti, võib tehnik teha omapoolseid helipakkumisi',
    'Tehnik on improviseerija, ja saab aru, mis etenduses impro, stseeni ja formaadi mõttes toimub',
];
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 2 / 7 · Tehniku standardinfo"
            title="Standardinfo"
            lead="Järgnev kehtib tüüpilise improetenduse kohta, ning ka siis, kui sa ei esita tehnikaplaani. Kirjelda erisoovid tehnikaplaanis!"
        />

        <div
            class="rounded-[22px] bg-r10-navy px-5 py-6 text-white sm:px-8 sm:py-[30px]"
        >
            <ul class="m-0 flex list-none flex-col gap-4 p-0">
                <li
                    v-for="(point, index) in points"
                    :key="index"
                    class="flex items-start gap-3.5"
                >
                    <span
                        class="mt-1.5 h-2.5 w-2.5 shrink-0 rotate-45 rounded-[1px] bg-r10-orange"
                    />
                    <span
                        class="text-[15px] leading-relaxed text-r10-navy-100"
                        >{{ point }}</span
                    >
                </li>
            </ul>
        </div>

        <div class="mt-[30px] border-t border-r10-grey-200 pt-6">
            <div
                class="mb-1 font-r10-display text-base font-semibold tracking-[0.03em] text-r10-ink uppercase"
            >
                Ruumi tehniline võimekus
            </div>
            <p class="mt-0 mb-[18px] text-r10-grey-500">
                Tehnilise raideri dokumendist saad lisainfot esinemisruumis
                oleva tehnika ja paigutuse kohta. Tutvu sellega, kui sul on
                spetsiifilised soovid, ja esined Ruutu10 Improkeskuses.
            </p>
            <p class="mt-0 mb-[18px] text-center text-r10-grey-500">
                <a
                    href="https://ruutu10.ee/wp-content/uploads/2025/07/Improkeskuse-tehniline-raider-1.pdf"
                    target="_blank"
                    class="text-r10-orange underline hover:no-underline"
                >
                    Vaata tehnilist raiderit
                </a>
            </p>
        </div>
    </section>
</template>
