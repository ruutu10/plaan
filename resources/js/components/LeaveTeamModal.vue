<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmVisit from '@/components/technical-plan/R10ConfirmVisit.vue';
import { leave as leaveTeamAction } from '@/routes/teams';
import type { Team } from '@/types';

const props = defineProps<{ team: Team | null }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() =>
    props.team ? leaveTeamAction(props.team.slug) : null,
);
</script>

<template>
    <R10ConfirmVisit
        v-model:open="open"
        title="Lahku tiimist"
        confirm-label="Lahku"
        :action="action"
        test-id="leave-team-confirm"
    >
        <template #description>
            Kas soovid kindlasti lahkuda tiimist
            <strong>{{ team?.name }}</strong
            >? Tiimi formaadid ja plaanid jäävad alles.
        </template>
    </R10ConfirmVisit>
</template>
