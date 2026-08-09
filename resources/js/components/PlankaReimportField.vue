<script setup lang="ts">
/**
 * The one choice a deletion offers: whether the record is merely put aside or
 * wiped outright.
 *
 * The two differ only in what the weekly Planka import makes of them afterwards.
 * A record put aside leaves a row behind, and the import passes over a name or a
 * night it already has a row for, deleted or not — so a card still on the board
 * stops producing it. A record wiped leaves nothing to recognise, and the next
 * run announces it again from the same card.
 *
 * Ticked — put aside — is the default, because a deletion is nearly always meant
 * to stick, and the import undoing it a week later is the surprise worth guarding
 * against.
 */
const keepDeleted = defineModel<boolean>({ required: true });
</script>

<template>
    <label
        class="flex cursor-pointer items-start gap-3 rounded-lg border-2 border-r10-grey-200 bg-white p-4"
    >
        <input
            v-model="keepDeleted"
            type="checkbox"
            data-test="delete-keep-deleted"
            class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-r10-orange"
        />
        <span class="flex flex-col gap-0.5">
            <span
                class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
            >
                Kustuta, ja ära uuesti Plankast impordi
            </span>
            <span class="text-xs text-r10-grey-500">
                Linnukesega jääb kirje andmebaasi peidetuks ja järgmised Planka
                impordid ei loo seda uuesti. Ilma linnukeseta kustutatakse kirje
                jäädavalt ning Planka import võib selle sama kaardi pealt uuesti
                luua.
            </span>
        </span>
    </label>
</template>
