<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmVisit from '@/components/technical-plan/R10ConfirmVisit.vue';
import { destroy as destroyMember } from '@/routes/teams/members';
import type { Team, TeamMember } from '@/types';

const props = defineProps<{ team: Team; member: TeamMember | null }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() =>
    props.member ? destroyMember([props.team.slug, props.member.id]) : null,
);
</script>

<template>
    <R10ConfirmVisit
        v-model:open="open"
        title="Eemalda liige"
        confirm-label="Eemalda"
        :action="action"
        test-id="remove-member-confirm"
    >
        <template #description>
            Kas eemaldada <strong>{{ member?.name }}</strong> tiimist? Konto ise
            jääb alles ja ta saab tiimi hiljem tagasi lisada.
        </template>
    </R10ConfirmVisit>
</template>
