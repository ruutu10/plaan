<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PasswordInput from '@/components/PasswordInput.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Lähtesta parool',
        description: 'Sisesta oma uus parool',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Lähtesta parool" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <R10Input
                id="email"
                type="email"
                name="email"
                label="E-post"
                autocomplete="email"
                v-model="inputEmail"
                readonly
                :error="errors.email"
            />

            <PasswordInput
                id="password"
                name="password"
                label="Parool"
                autocomplete="new-password"
                autofocus
                placeholder="Parool"
                :passwordrules="passwordRules"
                :error="errors.password"
            />

            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                label="Kinnita parool"
                autocomplete="new-password"
                placeholder="Kinnita parool"
                :passwordrules="passwordRules"
                :error="errors.password_confirmation"
            />

            <R10Button
                type="submit"
                size="lg"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                Lähtesta parool
            </R10Button>
        </div>
    </Form>
</template>
