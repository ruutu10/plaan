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
        title: 'Loo konto',
        description: 'Sisesta oma andmed, et konto luua',
    },
});
</script>

<template>
    <Head title="Registreeru" />

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="register"
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
                label="Nimi"
                required
                autofocus
                :tabindex="1"
                autocomplete="name"
                placeholder="Ees- ja perekonnanimi"
                :error="errors.name"
            />

            <R10Input
                id="email"
                type="email"
                name="email"
                label="E-post"
                required
                :tabindex="2"
                autocomplete="email"
                placeholder="email@example.com"
                :error="errors.email"
            />

            <PasswordInput
                id="password"
                name="password"
                label="Parool"
                required
                :tabindex="3"
                autocomplete="new-password"
                placeholder="Parool"
                :passwordrules="passwordRules"
                :error="errors.password"
            />

            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                label="Kinnita parool"
                required
                :tabindex="4"
                autocomplete="new-password"
                placeholder="Kinnita parool"
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
                Loo konto
            </R10Button>
        </div>

        <div class="text-center text-sm text-r10-grey-500">
            Kas sul on juba konto?
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
                Logi sisse
            </TextLink>
        </div>
    </Form>
</template>
