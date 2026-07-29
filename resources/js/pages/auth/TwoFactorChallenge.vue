<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Taastekood',
            description:
                'Kinnita ligipääs oma kontole, sisestades ühe oma varukoodidest.',
            buttonText: 'logi sisse autentimiskoodiga',
        };
    }

    return {
        title: 'Autentimiskood',
        description:
            'Sisesta oma autentimisrakenduse pakutav autentimiskood.',
        buttonText: 'logi sisse taastekoodiga',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Kaheastmeline autentimine" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <R10Button
                    type="submit"
                    size="lg"
                    class="w-full"
                    :disabled="processing"
                >
                    Jätka
                </R10Button>
                <div class="text-center text-sm text-r10-grey-500">
                    <span>või sisesta selle asemel </span>
                    <button
                        type="button"
                        class="cursor-pointer font-medium text-r10-orange underline underline-offset-4 transition-colors hover:text-r10-orange-700"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <R10Input
                    name="recovery_code"
                    type="text"
                    label="Taastekood"
                    placeholder="Sisesta taastekood"
                    :autofocus="showRecoveryInput"
                    required
                    :error="errors.recovery_code"
                />
                <R10Button
                    type="submit"
                    size="lg"
                    class="w-full"
                    :disabled="processing"
                >
                    Jätka
                </R10Button>

                <div class="text-center text-sm text-r10-grey-500">
                    <span>või sisesta selle asemel </span>
                    <button
                        type="button"
                        class="cursor-pointer font-medium text-r10-orange underline underline-offset-4 transition-colors hover:text-r10-orange-700"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
