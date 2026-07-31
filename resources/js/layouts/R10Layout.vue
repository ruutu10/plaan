<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppFooter from '@/components/AppFooter.vue';
import { dashboard, login, logout } from '@/routes';

withDefaults(
    defineProps<{
        title?: string;
        noPrintHeader?: boolean;
    }>(),
    {
        title: '',
        noPrintHeader: false,
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);
</script>

<template>
    <div class="min-h-screen bg-r10-paper font-r10-body text-r10-grey-700">
        <header
            class="sticky top-0 z-20 border-b border-white/15 bg-r10-navy"
            :class="{ 'r10-no-print': noPrintHeader }"
        >
            <div
                class="mx-auto flex max-w-[1160px] items-center gap-5 px-6 py-3.5"
            >
                <Link
                    href="/"
                    class="font-r10-display text-xl font-black tracking-[0.06em] text-white"
                >
                    RUUTU<span class="text-r10-orange">10</span>
                </Link>

                <template v-if="title">
                    <div class="h-6 w-px bg-white/15" />
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span
                            class="font-r10-display text-[15px] leading-none font-semibold tracking-[0.03em] text-white uppercase"
                        >
                            {{ title }}
                        </span>
                    </div>
                </template>

                <div class="ml-auto flex items-center gap-4">
                    <slot name="actions" />

                    <nav v-if="user" class="flex items-center gap-3">
                        <Link
                            :href="dashboardUrl"
                            class="font-r10-body text-xs font-bold tracking-[0.06em] text-white/80 uppercase transition hover:text-r10-orange"
                        >
                            Töölaud
                        </Link>
                        <span
                            class="hidden max-w-[180px] truncate border-l border-white/15 pl-3 font-r10-body text-sm font-semibold text-white sm:inline"
                            :title="user.name"
                        >
                            {{ user.name }}
                        </span>
                        <Link
                            :href="logout()"
                            as="button"
                            class="cursor-pointer font-r10-body text-xs font-bold tracking-[0.06em] text-white/70 uppercase transition hover:text-r10-orange"
                            @click="() => router.flushAll()"
                        >
                            Logi välja
                        </Link>
                    </nav>

                    <Link
                        v-else
                        :href="login()"
                        class="rounded-full bg-r10-orange px-5 py-2 font-r10-body text-xs font-bold tracking-[0.06em] text-r10-navy uppercase transition hover:bg-r10-orange-600"
                    >
                        Logi sisse
                    </Link>
                </div>
            </div>
        </header>

        <slot />

        <AppFooter />
    </div>
</template>
