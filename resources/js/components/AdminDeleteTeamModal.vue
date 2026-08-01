<script setup lang="ts">
import { computed } from 'vue';
import R10ConfirmDelete from '@/components/technical-plan/R10ConfirmDelete.vue';
import { destroy } from '@/routes/api/teams';
import type { ManagedTeam } from '@/types';

const props = defineProps<{ team: ManagedTeam | null }>();

const emit = defineEmits<{ deleted: [] }>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() => (props.team ? destroy(props.team.id) : null));

const memberCount = computed(() => props.team?.memberCount ?? 0);
const showCount = computed(() => props.team?.showCount ?? 0);
</script>

<template>
    <R10ConfirmDelete
        v-model:open="open"
        title="Kustuta tiim"
        :action="action"
        success-toast="Tiim kustutatud."
        test-id-prefix="team-delete"
        @deleted="emit('deleted')"
    >
        <template #description>
            Kas kustutada tiim „{{ team?.name }}“? See kaob nimekirjast, kuid
            jääb andmebaasi alles.
        </template>

        <template v-if="memberCount > 0 || showCount > 0" #warning>
            Tiimi {{ memberCount }} liiget kaotavad ligipääsu ja viiakse üle
            mõnda teise oma tiimi, kui neil see on. Tiimi {{ showCount }}
            lavastust jäävad alles, kuid ilma tiimita neid enam ei halda.
        </template>
    </R10ConfirmDelete>
</template>
