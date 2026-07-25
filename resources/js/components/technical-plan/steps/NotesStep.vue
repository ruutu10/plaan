<script setup lang="ts">
import { computed } from 'vue';
import type { PlanFile } from '@/types/technicalPlan';
import {
    acceptAttribute,
    discardAttachment,
    extensionHint as spellOutExtensions,
    uploadAttachment,
    validationError,
} from '../attachments';
import Diamond from '../Diamond.vue';
import { formatFileSize, uid } from '../plan';
import { usePlan, useWizardConfig } from '../planKey';
import R10Textarea from '../R10Textarea.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();
const config = useWizardConfig();

const accept = computed(() => acceptAttribute(config.allowedExtensions));

const extensionHint = computed(() =>
    spellOutExtensions(config.allowedExtensions),
);

async function uploadFile(file: File): Promise<void> {
    const entry: PlanFile = {
        id: '',
        name: file.name,
        size: file.size,
        tempKey: uid(),
        status: 'uploading',
    };
    // Track the reactive proxy Vue stores in the array — mutating the raw
    // `entry` after push would not trigger a re-render.
    plan.extra.files.push(entry);
    const tracked = plan.extra.files[plan.extra.files.length - 1];

    const error = validationError(file, config, config.allowedExtensions);

    if (error) {
        tracked.status = 'error';
        tracked.error = error;

        return;
    }

    Object.assign(tracked, await uploadAttachment(file));
}

async function onFiles(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';

    await Promise.all(files.map((file) => uploadFile(file)));
}

async function removeFile(index: number): Promise<void> {
    const [removed] = plan.extra.files.splice(index, 1);

    await discardAttachment(removed?.id ?? '');
}
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 6 / 7 · Lisainfo"
            title="Lisainfo & failid"
            lead="Vabas vormis, pole kohustuslik. Kõik muu vajaminev info ning failid."
        />

        <R10Textarea
            v-model="plan.extra.notes"
            label="Märkused"
            placeholder="Kirjuta siia kõik, mida tehnik peaks veel teadma…"
            min-height="150px"
        />

        <div class="mt-6">
            <div
                class="mb-2.5 font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
            >
                Failid
            </div>
            <label
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-[14px] border-2 border-dashed border-r10-navy-300 bg-r10-grey-100 p-8 text-center transition hover:border-r10-orange hover:bg-r10-orange-100"
            >
                <Diamond :size="14" />
                <span class="font-r10-body text-[15px] font-bold text-r10-navy">
                    Lohista failid siia või vali
                </span>
                <span class="text-xs text-r10-grey-500">
                    Lubatud: {{ extensionHint }}
                </span>
                <input
                    type="file"
                    multiple
                    class="hidden"
                    :accept="accept"
                    @change="onFiles"
                />
            </label>

            <div
                v-if="plan.extra.files.length"
                class="mt-3.5 flex flex-col gap-2"
            >
                <div
                    v-for="(file, index) in plan.extra.files"
                    :key="file.tempKey ?? file.id"
                    class="flex items-center gap-3 rounded-[10px] border bg-white px-3.5 py-2.5"
                    :class="
                        file.status === 'error'
                            ? 'border-r10-error'
                            : 'border-r10-grey-200'
                    "
                >
                    <span
                        v-if="file.status === 'uploading'"
                        class="h-2 w-2 shrink-0 rotate-45 animate-[r10spin_1s_linear_infinite] rounded-[1px] bg-r10-orange"
                    />
                    <Diamond v-else :size="8" />
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span
                            class="overflow-hidden text-sm font-medium text-ellipsis whitespace-nowrap text-r10-ink"
                        >
                            {{ file.name }}
                        </span>
                        <span
                            v-if="file.status === 'ready' && file.url"
                            class="flex items-center gap-3 text-xs"
                        >
                            <a
                                :href="file.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange"
                            >
                                Ava
                            </a>
                            <a
                                v-if="file.downloadUrl"
                                :href="file.downloadUrl"
                                class="font-medium text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange"
                            >
                                Laadi alla
                            </a>
                        </span>
                        <span
                            v-else-if="file.status === 'error'"
                            class="text-xs text-r10-error"
                        >
                            {{ file.error }}
                        </span>
                        <span
                            v-else-if="file.status === 'uploading'"
                            class="text-xs text-r10-grey-500"
                        >
                            Laen üles…
                        </span>
                    </span>
                    <span
                        v-if="file.status !== 'error'"
                        class="shrink-0 text-xs text-r10-grey-500"
                        >{{ formatFileSize(file.size) }}</span
                    >
                    <button
                        type="button"
                        title="Eemalda"
                        class="shrink-0 cursor-pointer border-none bg-transparent text-[15px] leading-none text-r10-grey-500 transition hover:text-r10-error"
                        @click="removeFile(index)"
                    >
                        ✕
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
