<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Diamond from '@/components/technical-plan/Diamond.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Layout from '@/layouts/R10Layout.vue';
import { dashboard, login, register } from '@/routes';
import { index as technicalPlan } from '@/routes/technical-plan';

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);
</script>

<template>

    <Head title="RUUTU10" />

    <R10Layout>
        <template #actions>
            <nav class="flex items-center gap-3">
                <Link v-if="page.props.auth.user" :href="dashboardUrl"
                    class="font-r10-body text-xs font-bold tracking-[0.06em] text-white/80 uppercase transition hover:text-r10-orange">
                    Töölaud
                </Link>
                <template v-else>
                    <Link :href="login()"
                        class="font-r10-body text-xs font-bold tracking-[0.06em] text-white/80 uppercase transition hover:text-r10-orange">
                        Logi sisse
                    </Link>
                    <Link :href="register()"
                        class="rounded-full bg-r10-orange px-5 py-2 font-r10-body text-xs font-bold tracking-[0.06em] text-r10-navy uppercase transition hover:bg-r10-orange-600">
                        Registreeri
                    </Link>
                </template>
            </nav>
        </template>

        <main class="mx-auto max-w-[1160px] px-6 pt-16 pb-16">
            <div
                class="mx-auto max-w-[640px] rounded-[22px] border border-r10-grey-200 bg-white p-8 shadow-[0_6px_18px_rgba(10,14,23,0.1)] sm:p-12">
                <div class="mb-5 flex items-center gap-2">
                    <Diamond :size="8" />
                    <span class="text-xs font-bold tracking-[0.1em] text-r10-grey-500 uppercase">
                        Esinejatele
                    </span>
                </div>

                <h1 class="font-r10-display text-3xl font-black tracking-[0.02em] text-r10-navy sm:text-4xl">
                    Tehnikaplaani esitamine
                </h1>

                <p class="mt-4 text-[15px] leading-relaxed text-r10-grey-700">
                    Kui sul on Ruutu10 laval etendus tulemas, ja soovid meile
                    saata oma tehnikaplaani, siis saad siin täita tehnikaplaani vormi.</p>
                <p class="mt-4 text-[15px] leading-relaxed text-r10-grey-700">
                    Nii teame juba
                    ette, millist heli, valgust ja tehnikat sinu etendus vajab.
                </p>

                <div class="mt-8">
                    <Link :href="technicalPlan()">
                        <R10Button variant="primary" size="lg">
                            Esita tehnikaplaan
                        </R10Button>
                    </Link>
                </div>
            </div>
        </main>
    </R10Layout>
</template>
