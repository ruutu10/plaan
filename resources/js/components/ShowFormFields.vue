<script setup lang="ts">
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Textarea from '@/components/technical-plan/R10Textarea.vue';
import type { ShowFieldErrors, ShowTeamOption } from '@/types';

/**
 * The three fields a show is made of, shared by the page that corrects a show
 * and the modal that enters a new one, so the two never drift apart.
 */
type Props = {
    teams: ShowTeamOption[];
    errors: ShowFieldErrors;
    /** Prefix for the field ids, so two of these may sit on one page. */
    idPrefix?: string;
};

withDefaults(defineProps<Props>(), {
    idPrefix: 'show',
});

const teamId = defineModel<number | null>('teamId', { required: true });
const name = defineModel<string>('name', { required: true });
const description = defineModel<string>('description', { required: true });
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-1.5">
            <label
                :for="`${idPrefix}-team`"
                class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
            >
                Tiim
                <span class="text-r10-orange">*</span>
            </label>
            <span class="-mt-0.5 text-xs text-r10-grey-500">
                Tiim, kellele lavastus kuulub.
            </span>
            <select
                :id="`${idPrefix}-team`"
                v-model="teamId"
                data-test="show-team-select"
                class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange"
            >
                <option :value="null" disabled>Vali tiim</option>
                <option v-for="team in teams" :key="team.id" :value="team.id">
                    {{ team.name }}
                </option>
            </select>
            <span
                v-if="errors.team_id"
                class="text-xs font-medium text-r10-orange-700"
            >
                {{ errors.team_id }}
            </span>
        </div>

        <div class="flex flex-col gap-1.5">
            <R10Input
                v-model="name"
                label="Nimi"
                required
                placeholder="Lavastuse nimi"
            />
            <span
                v-if="errors.name"
                class="text-xs font-medium text-r10-orange-700"
            >
                {{ errors.name }}
            </span>
        </div>

        <div class="flex flex-col gap-1.5">
            <R10Textarea
                v-model="description"
                label="Kirjeldus"
                hint="Lühikirjeldus, mida lavastus endast kujutab."
            />
            <span
                v-if="errors.description"
                class="text-xs font-medium text-r10-orange-700"
            >
                {{ errors.description }}
            </span>
        </div>
    </div>
</template>
