<script setup lang="ts">
import { Send } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { formatLocalTimestamp } from '@/lib/date';
import { failureMessage, requestJson } from '@/lib/http';
import comments from '@/routes/technical-plan/comments';
import type { PlanComment } from '@/types/technicalPlan';
import R10Button from './R10Button.vue';
import R10Notice from './R10Notice.vue';
import R10SectionHeader from './R10SectionHeader.vue';
import R10Textarea from './R10Textarea.vue';

/**
 * The conversation about a plan, on the plan's own overview page — the last
 * step of the wizard, and the page a share link opens.
 *
 * The thread is read over the wizard's JSON API rather than being handed down
 * with the page: this page is served to guests too, and it is also where a plan
 * first gets a key of its own, so the conversation can only be asked for once
 * there is a plan to ask about.
 */
const props = defineProps<{
    /** The saved plan's key. The thread belongs to the plan, so there is none until there is one. */
    token: string;
}>();

const thread = ref<PlanComment[]>([]);
const loading = ref(false);
const loadError = ref('');

/**
 * Whether this reader may join in, as the server says — the same rule that
 * guards the endpoint, rather than a second opinion formed in the browser. A
 * guest reading a shared plan gets the thread and an invitation to log in.
 */
const canComment = ref(false);

const body = ref('');
const posting = ref(false);
const postError = ref('');

const hasComments = computed(() => thread.value.length > 0);

/** Nothing to send while the box is empty or holds only spaces. */
const canSend = computed(() => body.value.trim() !== '' && !posting.value);

async function load(token: string): Promise<void> {
    loading.value = true;
    loadError.value = '';

    const response = await requestJson(comments.index.url(token));

    loading.value = false;

    if (!response.ok) {
        loadError.value = failureMessage(
            response.status,
            response.data,
            'Kommentaare ei õnnestunud laadida.',
        );

        return;
    }

    thread.value = (response.data.results as PlanComment[]) ?? [];
    canComment.value = response.data.canComment === true;
}

async function send(): Promise<void> {
    if (!canSend.value) {
        return;
    }

    posting.value = true;
    postError.value = '';

    const response = await requestJson(
        comments.store.url(props.token),
        'POST',
        { body: body.value.trim() },
    );

    posting.value = false;

    if (!response.ok) {
        postError.value = failureMessage(
            response.status,
            response.data,
            'Kommentaari ei õnnestunud salvestada.',
        );

        return;
    }

    // Appended rather than re-fetched: the server answers with the comment as
    // it was stored, so the thread is already what a reload would show.
    thread.value = [...thread.value, response.data as unknown as PlanComment];
    body.value = '';
}

watch(() => props.token, load, { immediate: true });
</script>

<template>
    <section class="r10-no-print mt-[48px]" data-test="plan-comments">
        <R10SectionHeader
            title="Kommentaarid"
            lead="Siin saavad esineja ja tehnikatiim plaani üle täpsustada. Uuest kommentaarist saab teine pool e-kirja."
        />

        <R10Notice v-if="loading" tone="busy" class="mt-4">
            Laen kommentaare…
        </R10Notice>

        <R10Notice v-else-if="loadError" class="mt-4">
            {{ loadError }}
        </R10Notice>

        <template v-else>
            <ol
                v-if="hasComments"
                class="mt-5 flex list-none flex-col gap-3.5 p-0"
            >
                <li
                    v-for="comment in thread"
                    :key="comment.id"
                    class="rounded-[14px] border border-r10-grey-200 bg-white px-4 py-3.5 sm:px-[18px]"
                    data-test="plan-comment"
                >
                    <div
                        class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1"
                    >
                        <span
                            class="font-r10-body text-sm font-bold text-r10-ink"
                        >
                            {{ comment.authorName }}
                        </span>
                        <!-- Which side wrote it is the first thing a reader
                             needs; the crew's remarks carry the house's word. -->
                        <span
                            v-if="comment.fromTechnicalTeam"
                            class="rounded-full bg-r10-navy px-2.5 py-0.5 font-r10-body text-[10px] font-bold tracking-[0.08em] text-white uppercase"
                        >
                            Tehnikatiim
                        </span>
                        <span class="ml-auto text-xs text-r10-grey-500">
                            {{ formatLocalTimestamp(comment.createdAt) }}
                        </span>
                    </div>
                    <p
                        class="mt-1.5 mb-0 text-[15px] leading-relaxed break-words whitespace-pre-wrap text-r10-ink"
                    >
                        {{ comment.body }}
                    </p>
                </li>
            </ol>

            <div v-if="canComment" class="mt-5">
                <R10Textarea
                    v-model="body"
                    label="Lisa kommentaar"
                    placeholder="Kirjuta, mis plaani juures täpsustamist vajab…"
                    min-height="96px"
                    :disabled="posting"
                    data-test="plan-comment-body"
                />
                <div
                    class="mt-3 flex flex-wrap items-center justify-end gap-3.5"
                >
                    <R10Button
                        variant="primary"
                        size="md"
                        :disabled="!canSend"
                        data-test="plan-comment-submit"
                        @click="send"
                    >
                        {{ posting ? 'Saadan…' : 'Lisa kommentaar' }}
                        <Send class="h-4 w-4" aria-hidden="true" />
                    </R10Button>
                </div>
            </div>

            <!-- A guest holding the share link reads the conversation but has
                 no name to write under; logging in is what gives them one. -->
            <p
                v-else
                class="mt-5 mb-0 text-[15px] text-r10-grey-500"
                data-test="plan-comments-locked"
            >
                Kommenteerimiseks palun logi sisse.
            </p>

            <R10Notice v-if="postError" class="mt-4">
                {{ postError }}
            </R10Notice>
        </template>
    </section>
</template>
