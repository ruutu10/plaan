<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Diamond from '@/components/technical-plan/Diamond.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Layout from '@/layouts/R10Layout.vue';
import { login } from '@/routes';
import { index as technicalPlan } from '@/routes/technical-plan';
</script>

<template>
    <Head title="RUUTU10" />

    <R10Layout>
        <main class="mx-auto max-w-[1160px] px-6 pt-16 pb-16">
            <div
                class="mx-auto grid max-w-[640px] gap-8 lg:max-w-none lg:grid-cols-2"
            >
                <div
                    class="flex flex-col rounded-[22px] border border-r10-grey-200 bg-white p-8 shadow-[0_6px_18px_rgba(10,14,23,0.1)] sm:p-12"
                >
                    <div class="mb-5 flex items-center gap-2">
                        <Diamond :size="8" />
                        <span
                            class="text-xs font-bold tracking-[0.1em] text-r10-grey-500 uppercase"
                        >
                            Esinejatele
                        </span>
                    </div>

                    <h1
                        class="font-r10-display text-3xl font-black tracking-[0.02em] text-r10-navy sm:text-4xl"
                    >
                        Tehnikaplaani esitamine
                    </h1>

                    <p
                        class="mt-4 text-[15px] leading-relaxed text-r10-grey-700"
                    >
                        Kui sul on Ruutu10 laval etendus tulemas, ja soovid
                        meile saata oma tehnikaplaani, siis saad siin täita
                        tehnikaplaani vormi.
                    </p>
                    <p
                        class="mt-4 text-[15px] leading-relaxed text-r10-grey-700"
                    >
                        Nii teame juba ette, millist heli, valgust ja tehnikat
                        sinu etendus vajab.
                    </p>

                    <div class="mt-auto pt-8">
                        <Link :href="technicalPlan()">
                            <R10Button variant="primary" size="lg">
                                Esita tehnikaplaan
                            </R10Button>
                        </Link>
                    </div>
                </div>

                <div
                    class="flex flex-col rounded-[22px] border border-r10-grey-200 bg-white p-8 shadow-[0_6px_18px_rgba(10,14,23,0.1)] sm:p-12"
                >
                    <div class="mb-5 flex items-center gap-2">
                        <Diamond :size="8" />
                        <span
                            class="text-xs font-bold tracking-[0.1em] text-r10-grey-500 uppercase"
                        >
                            Tehnikutele
                        </span>
                    </div>

                    <h2
                        class="font-r10-display text-3xl font-black tracking-[0.02em] text-r10-navy sm:text-4xl"
                    >
                        Saadetud tehnikaplaanide vaatamine
                    </h2>

                    <p
                        class="mt-4 text-[15px] leading-relaxed text-r10-grey-700"
                    >
                        Logi sisse, et näha esinejate saadetud tehnikaplaane.
                    </p>

                    <div class="mt-auto pt-8">
                        <Link :href="login()">
                            <R10Button
                                variant="outline"
                                size="lg"
                                data-test="technicians-login-button"
                            >
                                Logi sisse
                            </R10Button>
                        </Link>
                    </div>
                </div>
            </div>
        </main>
    </R10Layout>
</template>
