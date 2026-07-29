<script setup lang="ts">
import { computed } from 'vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import R10Textarea from '@/components/technical-plan/R10Textarea.vue';
import type { ShowFieldErrors, ShowTeamOption } from '@/types';

/**
 * The three fields a show is made of, shared by the page that corrects a show
 * and the modal that enters a new one, so the two never drift apart.
 */
const props = defineProps<{
    teams: ShowTeamOption[];
    errors: ShowFieldErrors;
}>();

const teamId = defineModel<number | null>('teamId', { required: true });
const name = defineModel<string>('name', { required: true });
const description = defineModel<string>('description', { required: true });

const teamOptions = computed(() =>
    props.teams.map((team) => ({ value: team.id, label: team.name })),
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <R10Select
            v-model="teamId"
            label="Tiim"
            required
            hint="Tiim, kellele lavastus kuulub."
            placeholder="Vali tiim"
            :options="teamOptions"
            :error="errors.team_id"
            data-test="show-team-select"
        />

        <R10Input
            v-model="name"
            label="Nimi"
            required
            placeholder="Lavastuse nimi"
            :error="errors.name"
        />

        <R10Textarea
            v-model="description"
            label="Kirjeldus"
            hint="Lühikirjeldus, mida lavastus endast kujutab."
            :error="errors.description"
        />
    </div>
</template>
