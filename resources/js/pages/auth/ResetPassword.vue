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
        title: 'Reset password',
        description: 'Please enter your new password below',
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
    <Head title="Reset password" />

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
                label="Email"
                autocomplete="email"
                v-model="inputEmail"
                readonly
                :error="errors.email"
            />

            <PasswordInput
                id="password"
                name="password"
                label="Password"
                autocomplete="new-password"
                autofocus
                placeholder="Password"
                :passwordrules="passwordRules"
                :error="errors.password"
            />

            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                label="Confirm password"
                autocomplete="new-password"
                placeholder="Confirm password"
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
                Reset password
            </R10Button>
        </div>
    </Form>
</template>
