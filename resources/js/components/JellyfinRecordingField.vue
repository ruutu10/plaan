<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import R10Input from '@/components/technical-plan/R10Input.vue';

/**
 * Where the video of this night lives in the house's Jellyfin library. The crew
 * pastes the episode's address off Jellyfin itself; nothing fills it
 * automatically.
 *
 * The status line under the field is the point of it being more than an input:
 * saying where a video is starts a push of the night's details onto that
 * episode, and a push runs on a queue, so without a word here it would be
 * impossible to tell a link that worked from one that quietly failed.
 */
const props = withDefaults(
    defineProps<{
        /** The saved episode as a link, when there is one and a library to open. */
        itemUrl?: string | null;
        /** When the night's details last reached Jellyfin; ISO 8601. */
        syncedAt?: string | null;
        /** What stopped the last push, when one failed. */
        syncError?: string | null;
        error?: string;
        disabled?: boolean;
    }>(),
    {
        itemUrl: null,
        syncedAt: null,
        syncError: null,
        disabled: false,
    },
);

const url = defineModel<string>({ required: true });

/**
 * How the last push went, in the words the crew reads. Nothing at all before a
 * link has been saved — there is no push to report on yet.
 */
const status = computed<{ text: string; failed: boolean } | null>(() => {
    if (props.syncError) {
        return { text: 'Jellyfini uuendamine ebaõnnestus.', failed: true };
    }

    if (props.syncedAt) {
        return {
            text: `Andmed uuendatud Jellyfinis ${new Date(props.syncedAt).toLocaleString('et-EE', { dateStyle: 'short', timeStyle: 'short' })}.`,
            failed: false,
        };
    }

    if (props.itemUrl) {
        return { text: 'Andmete uuendamine Jellyfinis on järjekorras.', failed: false };
    }

    return null;
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <R10Input
            v-model="url"
            label="Jellyfini salvestuse link"
            hint="Kleebi video aadress Jellyfini veebiliidesest. Etenduse andmed — esinejad, kuupäev, asukoht — uuendatakse Jellyfinis automaatselt."
            placeholder="Nt https://jellyfin.r10.ee/web/#/details?id=..."
            data-test="jellyfin-link-input"
            :disabled="disabled"
            :error="error"
        />

        <!-- The saved episode, not the one being typed: the link is only good
             once the address has been written down. -->
        <a
            v-if="itemUrl"
            :href="itemUrl"
            target="_blank"
            rel="noopener noreferrer"
            data-test="jellyfin-link"
            class="inline-flex w-fit items-center gap-1.5 text-xs font-medium text-r10-navy underline underline-offset-2 hover:text-r10-orange-700"
        >
            <ExternalLink class="h-3.5 w-3.5" />
            Ava salvestus Jellyfinis
        </a>

        <p
            v-if="status"
            data-test="jellyfin-sync-status"
            class="text-xs"
            :class="status.failed ? 'text-r10-orange-700' : 'text-gray-500'"
        >
            {{ status.text }}
        </p>
    </div>
</template>
