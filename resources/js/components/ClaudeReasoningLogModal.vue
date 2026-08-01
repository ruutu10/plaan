<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { ref, watch } from 'vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Dialog from '@/components/technical-plan/R10Dialog.vue';
import { formatEstonianDate } from '@/lib/date';
import { show as reasoningLogApi } from '@/routes/api/claude-logs';
import type { ClaudeReasoningLog } from '@/types';

/**
 * What the AI made of the Planka card a record was imported from: where it took
 * the date, why it split an evening, why it left a group off. A debugging aid
 * for the house's own people — the button that opens it is only rendered for
 * users the server told there was a log at all.
 */
const props = defineProps<{ logId: number | null }>();

const open = defineModel<boolean>('open', { required: true });

const http = useHttp();

const log = ref<ClaudeReasoningLog | null>(null);
const loadFailed = ref(false);

/**
 * Fetched when the dialog opens rather than with the page behind it: most rows
 * carry reasoning nobody ever asks for, and one modal is mounted per page and
 * reused for whichever row was clicked.
 */
watch([open, () => props.logId], async ([isOpen, logId]) => {
    if (!isOpen || logId === null) {
        return;
    }

    log.value = null;
    loadFailed.value = false;

    try {
        const response = (await http.submit(reasoningLogApi(logId))) as {
            data: ClaudeReasoningLog;
        };

        log.value = response.data;
    } catch {
        loadFailed.value = true;
    }
});
</script>

<template>
    <R10Dialog
        v-model:open="open"
        title="AI põhjendused"
        description="Miks Planka import selle kirje just nii lõi."
    >
        <!-- The marker sits on the body: `R10Dialog`'s root is renderless, so
             an attribute on the component itself would not reach the DOM. -->
        <div data-test="reasoning-log-modal" class="grid gap-4">
            <p
                v-if="loadFailed"
                data-test="reasoning-log-error"
                class="text-[15px] text-r10-orange-700"
            >
                Põhjenduste laadimine ebaõnnestus. Proovi uuesti.
            </p>

            <!-- Nothing has arrived yet: stand-ins the shape of a few notes. -->
            <div
                v-else-if="log === null"
                data-test="reasoning-log-skeleton"
                class="grid gap-3"
            >
                <span
                    v-for="line in 3"
                    :key="line"
                    class="block h-3 animate-pulse rounded-full bg-r10-grey-200"
                    :class="line === 3 ? 'w-2/3' : 'w-full'"
                />
            </div>

            <template v-else>
                <p
                    v-if="log.cardName || log.cardUrl"
                    data-test="reasoning-log-card"
                    class="text-[13px] text-r10-grey-500"
                >
                    Kaart
                    <!-- The board itself is where the argument can be checked,
                         so the card is a link wherever there is one to give. -->
                    <a
                        v-if="log.cardUrl"
                        :href="log.cardUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-test="reasoning-log-card-link"
                        class="inline-flex items-center gap-1 font-medium text-r10-navy underline underline-offset-2 hover:text-r10-orange-700"
                    >
                        „{{ log.cardName ?? log.cardId }}“
                        <ExternalLink class="h-3 w-3" />
                    </a>
                    <template v-else>„{{ log.cardName }}“</template>
                    <template v-if="log.readAt">
                        · loetud
                        {{ formatEstonianDate(log.readAt.slice(0, 10)) }}
                    </template>
                </p>

                <ul
                    v-if="log.notes.length > 0"
                    class="grid list-disc gap-2 pl-5 text-[15px] text-r10-grey-700"
                >
                    <li
                        v-for="(note, index) in log.notes"
                        :key="index"
                        data-test="reasoning-log-note"
                    >
                        {{ note }}
                    </li>
                </ul>

                <p v-else class="text-[15px] text-r10-grey-500">
                    Selle kirje kohta pole põhjendusi.
                </p>
            </template>
        </div>

        <template #actions>
            <R10Button
                variant="outline"
                data-test="reasoning-log-close"
                @click="open = false"
            >
                Sulge
            </R10Button>
        </template>
    </R10Dialog>
</template>
