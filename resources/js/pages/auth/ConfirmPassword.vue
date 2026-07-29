<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Kinnita parool',
        description:
            'See on rakenduse turvaline ala. Enne jätkamist kinnita oma parool.',
    },
});
</script>

<template>
    <Head title="Kinnita parool" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Kinnita pääsuvõtmega"
        loading-label="Kinnitan..."
        separator="Või kinnita parooliga"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <PasswordInput
                id="password"
                name="password"
                label="Parool"
                required
                autocomplete="current-password"
                autofocus
                :error="errors.password"
            />

            <div class="flex items-center">
                <R10Button
                    type="submit"
                    size="lg"
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    Kinnita parool
                </R10Button>
            </div>
        </div>
    </Form>
</template>
