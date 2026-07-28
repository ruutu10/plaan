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
import { uid } from '../plan';
import { usePlan, useWizardConfig } from '../planKey';
import R10Dropzone from '../R10Dropzone.vue';
import R10FileChip from '../R10FileChip.vue';
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

async function onFiles(files: FileList): Promise<void> {
    await Promise.all(Array.from(files).map((file) => uploadFile(file)));
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
            <R10Dropzone
                label="Lohista failid siia või vali"
                :hint="`Lubatud: ${extensionHint}`"
                :accept="accept"
                multiple
                @files="onFiles"
            />

            <div
                v-if="plan.extra.files.length"
                class="mt-3.5 flex flex-col gap-2"
            >
                <R10FileChip
                    v-for="(file, index) in plan.extra.files"
                    :key="file.tempKey ?? file.id"
                    :file="file"
                    @remove="removeFile(index)"
                />
            </div>
        </div>
    </section>
</template>
