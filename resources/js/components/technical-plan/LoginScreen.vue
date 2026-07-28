<script setup lang="ts">
import { ref } from 'vue';
import Diamond from './Diamond.vue';
import R10Button from './R10Button.vue';
import StepHeader from './StepHeader.vue';

const emit = defineEmits<{
    'send-link': [email: string];
}>();

const props = defineProps<{
    busy: boolean;
    sent: boolean;
    error: string;
    sentTo: string;
}>();

const email = ref('');

function submit(): void {
    emit('send-link', email.value);
}
</script>

<template>
    <section class="animate-[r10fade_0.38s_ease]">
        <StepHeader
            eyebrow="Samm 0 · Plaani koostaja tuvastamine"
            title="Plaani koostaja"
            lead="Tehnikaplaani esitamine algab esitaja tuvastamisest. Sisesta oma e-post - saadame sulle ühekordse lingi, millega plaani koostamist jätkata."
        />

        <!-- Confirmation: the magic link has been e-mailed. -->
        <div
            v-if="props.sent"
            class="rounded-[22px] border border-r10-grey-200 bg-r10-grey-100 p-[26px]"
        >
            <div class="flex items-start gap-3.5">
                <span
                    class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-r10-orange"
                >
                    <Diamond :size="12" />
                </span>
                <div class="min-w-0">
                    <div
                        class="mb-1.5 font-r10-display text-[18px] font-bold tracking-[0.03em] text-r10-ink uppercase"
                    >
                        Kontrolli oma postkasti
                    </div>
                    <p class="m-0 text-sm leading-relaxed text-r10-grey-700">
                        Saatsime sisselogimislingi aadressile
                        <span class="font-bold text-r10-ink">{{
                            props.sentTo
                        }}</span
                        >. Ava kiri ja vajuta lingil — suuname su siia tagasi ja
                        logime sisse. Link kehtib 30 minutit.
                    </p>
                </div>
            </div>
        </div>

        <!-- E-mail entry form. -->
        <form v-else @submit.prevent="submit">
            <div
                class="rounded-[22px] border border-r10-grey-200 bg-white p-[26px]"
            >
                <label class="flex flex-col gap-1.5">
                    <span
                        class="font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-ink uppercase"
                    >
                        E-post
                        <span class="text-r10-orange">*</span>
                    </span>
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="ando@ruutu10.ee"
                        autocomplete="email"
                        class="w-full rounded-lg border-2 border-r10-grey-200 bg-white px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange"
                    />
                </label>

                <div v-if="props.error" class="mt-3 flex items-start gap-2">
                    <span
                        class="mt-[5px] h-2 w-2 shrink-0 rotate-45 rounded-[1px] bg-r10-error"
                    />
                    <span class="text-[13px] leading-normal text-r10-error">{{
                        props.error
                    }}</span>
                </div>

                <R10Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    class="mt-5 w-full"
                    :disabled="props.busy"
                >
                    {{ props.busy ? 'Saadan…' : 'Saada link' }}
                </R10Button>
            </div>

            <p
                class="mt-3.5 flex items-center gap-2 text-[13px] leading-relaxed text-r10-grey-500"
            >
                <Diamond :size="7" />
                Kui sa pole varem tehnikaplaani esitanud, registreerime sinu
                emaili uue kasutajana.
            </p>
        </form>
    </section>
</template>
