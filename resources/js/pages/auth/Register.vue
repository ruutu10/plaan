<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { TeamInvitationContext } from '@/types';

defineProps<{
    passwordRules: string;
    teamInvitation?: TeamInvitationContext | null;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});
</script>

<template>
    <Head title="Register" />

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Register"
        class="mb-4"
    />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <R10Input
                id="name"
                type="text"
                name="name"
                label="Name"
                required
                autofocus
                :tabindex="1"
                autocomplete="name"
                placeholder="Full name"
                :error="errors.name"
            />

            <R10Input
                id="email"
                type="email"
                name="email"
                label="Email address"
                required
                :tabindex="2"
                autocomplete="email"
                placeholder="email@example.com"
                :error="errors.email"
            />

            <PasswordInput
                id="password"
                name="password"
                label="Password"
                required
                :tabindex="3"
                autocomplete="new-password"
                placeholder="Password"
                :passwordrules="passwordRules"
                :error="errors.password"
            />

            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                label="Confirm password"
                required
                :tabindex="4"
                autocomplete="new-password"
                placeholder="Confirm password"
                :passwordrules="passwordRules"
                :error="errors.password_confirmation"
            />

            <R10Button
                type="submit"
                size="lg"
                class="mt-2 w-full"
                :tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </R10Button>
        </div>

        <div class="text-center text-sm text-r10-grey-500">
            Already have an account?
            <TextLink
                :href="
                    teamInvitation
                        ? login.url({
                              query: {
                                  invitation: teamInvitation.code,
                              },
                          })
                        : login()
                "
                :tabindex="6"
                data-test="team-invitation-login-link"
            >
                Log in
            </TextLink>
        </div>
    </Form>
</template>
