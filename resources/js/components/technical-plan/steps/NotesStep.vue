<script setup lang="ts">
import Diamond from '../Diamond.vue';
import { formatFileSize } from '../plan';
import { usePlan } from '../planKey';
import R10Textarea from '../R10Textarea.vue';
import StepHeader from '../StepHeader.vue';

const plan = usePlan();

function onFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []).map((f) => ({
        name: f.name,
        size: f.size,
    }));
    plan.extra.files.push(...files);
    input.value = '';
}

function removeFile(index: number): void {
    plan.extra.files.splice(index, 1);
}
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 6 / 7 · Lisainfo"
            title="Lisainfo & failid"
            lead="Vabas vormis, pole kohustuslik. Kõik muu vajaminev info ning failid (helifailid, plaanid, viited)."
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
                    Helifailid, PDF-id, pildid — mis iganes tehnikule abiks
                </span>
                <input type="file" multiple class="hidden" @change="onFiles" />
            </label>

            <div
                v-if="plan.extra.files.length"
                class="mt-3.5 flex flex-col gap-2"
            >
                <div
                    v-for="(file, index) in plan.extra.files"
                    :key="index"
                    class="flex items-center gap-3 rounded-[10px] border border-r10-grey-200 bg-white px-3.5 py-2.5"
                >
                    <Diamond :size="8" />
                    <span
                        class="flex-1 overflow-hidden text-sm font-medium text-ellipsis whitespace-nowrap text-r10-ink"
                    >
                        {{ file.name }}
                    </span>
                    <span class="shrink-0 text-xs text-r10-grey-500">{{
                        formatFileSize(file.size)
                    }}</span>
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
