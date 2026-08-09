<script setup lang="ts">
import { computed } from 'vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import R10Textarea from '@/components/technical-plan/R10Textarea.vue';
import type { FormatFieldErrors, FormatTeamOption } from '@/types';

/**
 * The fields a format is made of, shared by the page that corrects a format and the
 * modal that enters a new one, so the two never drift apart.
 */
const props = withDefaults(
    defineProps<{
        teams: FormatTeamOption[];
        errors: FormatFieldErrors;
        /** Read-only, for whoever may open the format but not correct it. */
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const teamId = defineModel<number | null>('teamId', { required: true });
const name = defineModel<string>('name', { required: true });
const description = defineModel<string>('description', { required: true });
const technicalPlanMandatory = defineModel<boolean>('technicalPlanMandatory', {
    required: true,
});

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
            hint="Tiim, kellele formaat kuulub."
            placeholder="Vali tiim"
            :options="teamOptions"
            :error="errors.team_id"
            :disabled="disabled"
            data-test="format-team-select"
        />

        <R10Input
            v-model="name"
            label="Nimi"
            required
            placeholder="Formaadi nimi"
            :error="errors.name"
            :disabled="disabled"
        />

        <R10Textarea
            v-model="description"
            label="Kirjeldus"
            hint="Lühikirjeldus, mida formaat endast kujutab. Just struktuuri poolest (mitte turunduslik tekst), nt: Küsime publikult inspiratsiooni, ning teeme siis pool tundi edititeta monostseeni."
            :error="errors.description"
            :disabled="disabled"
        />

        <div class="flex flex-col gap-1.5">
            <label
                class="flex items-start gap-3 rounded-lg border-2 border-r10-grey-200 bg-white p-4"
                :class="disabled ? 'cursor-default' : 'cursor-pointer'"
            >
                <input
                    v-model="technicalPlanMandatory"
                    type="checkbox"
                    :disabled="disabled"
                    data-test="format-technical-plan-mandatory-toggle"
                    class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-r10-orange disabled:cursor-default"
                />
                <span class="flex flex-col gap-0.5">
                    <span
                        class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
                    >
                        Tehnikaplaan on kohustuslik
                    </span>
                    <span class="text-xs text-r10-grey-500">
                        Eemalda linnuke formaatidel, mis tehnikat ei vaja (nt
                        õppelava või jämm). Nende etenduste kohta ei saadeta
                        meeldetuletuskirju puuduva tehnikaplaani pärast, kuid
                        plaani saab soovi korral ikka saata.
                    </span>
                </span>
            </label>
            <span
                v-if="errors.technical_plan_mandatory"
                class="text-xs font-medium text-r10-orange-700"
            >
                {{ errors.technical_plan_mandatory }}
            </span>
        </div>
    </div>
</template>
