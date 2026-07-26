<script setup lang="ts">
import { Head, Link, setLayoutProps, useHttp } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Textarea from '@/components/technical-plan/R10Textarea.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { show as showApi, update } from '@/routes/api/shows';
import { edit, index } from '@/routes/shows';
import type { BreadcrumbItem, Show, ShowTeamOption } from '@/types';

type Props = {
    showId: number;
};

const props = defineProps<Props>();

/** Null until the show lands, which is what the skeleton keys off. */
const show = ref<Show | null>(null);
const teams = ref<ShowTeamOption[]>([]);
const loadFailed = ref(false);

const loader = useHttp();

const form = useHttp({
    team_id: null as number | null,
    name: '',
    description: '',
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Lavastused',
                href: index(),
            },
        ],
    },
});

/**
 * Name the show in the breadcrumbs once it is known — the page is rendered as a
 * shell, so the trail starts one crumb short.
 */
function nameTheTrail(name: string): void {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Lavastused', href: index() },
            { title: name, href: edit(props.showId) },
        ],
    });
}

onMounted(async () => {
    try {
        const response = (await loader.submit(showApi(props.showId))) as {
            data: Show;
            teams: ShowTeamOption[];
        };

        show.value = response.data;
        teams.value = response.teams;

        form.team_id = response.data.teamId;
        form.name = response.data.name;
        form.description = response.data.description ?? '';
        form.defaults();

        nameTheTrail(response.data.name);
    } catch {
        loadFailed.value = true;
    }
});

async function save(): Promise<void> {
    try {
        const { data } = (await form.submit(update(props.showId))) as {
            data: Show;
        };

        show.value = data;
        form.defaults();

        nameTheTrail(data.name);

        toast.success('Lavastus salvestatud.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Salvestamine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <Head :title="show?.name ?? 'Lavastus'" />

    <div
        class="flex h-full flex-1 flex-col bg-r10-paper px-5 py-7 font-r10-body text-r10-grey-700 md:px-8 md:py-9"
    >
        <StepHeader
            eyebrow="Haldus"
            :title="show?.name ?? 'Lavastus'"
            lead="Muuda lavastuse andmeid. Muudatused kehtivad kõigile selle lavastuse etendustele."
        />

        <p
            v-if="loadFailed"
            class="max-w-2xl rounded-xl border-2 border-r10-grey-200 bg-white p-5 text-[15px] text-r10-orange-700 md:p-7"
        >
            Lavastuse laadimine ebaõnnestus. Proovi lehte värskendada.
        </p>

        <!-- Nothing has arrived yet: stand-ins the shape of the three fields. -->
        <div
            v-else-if="show === null"
            data-test="show-form-skeleton"
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
        >
            <div v-for="field in 3" :key="field" class="flex flex-col gap-2">
                <span
                    class="block h-3 w-24 animate-pulse rounded-full bg-r10-grey-200"
                />
                <span
                    class="block animate-pulse rounded-lg bg-r10-grey-100"
                    :class="field === 3 ? 'h-32' : 'h-12'"
                />
            </div>
        </div>

        <form
            v-else
            class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
            @submit.prevent="save"
        >
            <label class="flex flex-col gap-1.5">
                <span
                    class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                >
                    Trupp
                    <span class="text-r10-orange">*</span>
                </span>
                <span class="-mt-0.5 text-xs text-r10-grey-500">
                    Trupp, kellele lavastus kuulub.
                </span>
                <select
                    v-model="form.team_id"
                    data-test="show-team-select"
                    class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange"
                >
                    <option :value="null" disabled>Vali trupp</option>
                    <option
                        v-for="team in teams"
                        :key="team.id"
                        :value="team.id"
                    >
                        {{ team.name }}
                    </option>
                </select>
                <span
                    v-if="form.errors.team_id"
                    class="text-xs font-medium text-r10-orange-700"
                >
                    {{ form.errors.team_id }}
                </span>
            </label>

            <div class="flex flex-col gap-1.5">
                <R10Input
                    v-model="form.name"
                    label="Nimi"
                    required
                    placeholder="Lavastuse nimi"
                />
                <span
                    v-if="form.errors.name"
                    class="text-xs font-medium text-r10-orange-700"
                >
                    {{ form.errors.name }}
                </span>
            </div>

            <div class="flex flex-col gap-1.5">
                <R10Textarea
                    v-model="form.description"
                    label="Kirjeldus"
                    hint="Lühikirjeldus, mida lavastus endast kujutab."
                />
                <span
                    v-if="form.errors.description"
                    class="text-xs font-medium text-r10-orange-700"
                >
                    {{ form.errors.description }}
                </span>
            </div>

            <div class="flex items-center gap-3">
                <R10Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="show-save-button"
                >
                    Salvesta
                </R10Button>

                <Link
                    :href="index()"
                    class="inline-flex items-center gap-2 font-r10-body text-xs font-bold tracking-[0.04em] text-r10-navy uppercase transition hover:text-r10-orange-700"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    Tagasi
                </Link>
            </div>
        </form>
    </div>
</template>
