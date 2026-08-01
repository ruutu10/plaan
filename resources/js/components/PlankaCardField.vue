<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import R10Input from '@/components/technical-plan/R10Input.vue';

/**
 * The card on the Planka board a record was announced on. The import fills it;
 * this is where it is corrected, or entered for a record that was staged by
 * hand and only later turned up on the board.
 *
 * The link beside it is the point of the field: the card is where the crew, the
 * bar rota and everything decided after the import ran are still being written.
 */
withDefaults(
    defineProps<{
        /** The saved card as a link, when there is one and a board to open. */
        cardUrl?: string | null;
        error?: string;
        disabled?: boolean;
    }>(),
    { cardUrl: null, disabled: false },
);

const cardId = defineModel<string>({ required: true });
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <R10Input
            v-model="cardId"
            label="Planka kaardi ID"
            hint="Kaart, mille pealt see kirje imporditi. Impordi täidab ise; käsitsi lisatud kirjel võib tühjaks jääda."
            placeholder="Nt 1234567890123456789"
            data-test="planka-card-input"
            :disabled="disabled"
            :error="error"
        />

        <!-- The saved card, not the one being typed: the link is only good once
             the id has been written down. -->
        <a
            v-if="cardUrl"
            :href="cardUrl"
            target="_blank"
            rel="noopener noreferrer"
            data-test="planka-card-link"
            class="inline-flex w-fit items-center gap-1.5 text-xs font-medium text-r10-navy underline underline-offset-2 hover:text-r10-orange-700"
        >
            <ExternalLink class="h-3.5 w-3.5" />
            Ava kaart Plankas
        </a>
    </div>
</template>
