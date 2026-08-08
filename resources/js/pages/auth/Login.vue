<script setup lang="ts">
import { Form, Head, useForm } from '@inertiajs/vue3';
import { Lock, LogIn, Mail } from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef } from 'vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Notice from '@/components/technical-plan/R10Notice.vue';
import TextLink from '@/components/TextLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { redirect as authentikRedirect } from '@/routes/auth/authentik';
import { magicLink, store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: {
        title: 'Logi sisse',
        description: 'Vali, kuidas soovid ennast tuvastada',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    ssoEnabled: boolean;
    teamInvitation?: TeamInvitationContext | null;
}>();

/**
 * The address is read here as well as submitted with the password form, because
 * the choice of how to log in — a mailed link or a password — only appears once
 * it looks like an address, and the mailed link is sent from this same field.
 */
const email = ref('');

/** Whether the visitor has asked for the password fields to be shown. */
const enteringPassword = ref(false);

const passwordField =
    useTemplateRef<InstanceType<typeof PasswordInput>>('passwordField');

/** Enough of an address to act on; the server has the last word on it. */
const emailLooksValid = computed(() =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()),
);

const linkForm = useForm({ email: '' });

function sendLoginLink(): void {
    if (!emailLooksValid.value || linkForm.processing) {
        return;
    }

    linkForm.email = email.value.trim();
    linkForm.submit(magicLink(), {
        preserveScroll: true,
        // The typed address, and the choice made about it, outlive the round
        // trip: only the flashed confirmation at the top of the page changes.
        preserveState: true,
    });
}

function enterPassword(): void {
    enteringPassword.value = true;

    nextTick(() => passwordField.value?.focus());
}

/**
 * With only the address on screen, Enter means "send me the link" rather than
 * a submit of the password form, which has no password in it yet.
 */
function submitOnEnter(event: KeyboardEvent): void {
    if (enteringPassword.value) {
        return;
    }

    event.preventDefault();
    sendLoginLink();
}
</script>

<template>
    <Head title="Logi sisse" />

    <R10Notice v-if="status" tone="success" class="mb-4">
        {{ status }}
    </R10Notice>

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="login"
        class="mb-4"
    />

    <PasskeyVerify>
        <template v-if="ssoEnabled" #alternatives>
            <!-- A real top-level navigation to an external OAuth URL, so an
                 `external` plain anchor and not Inertia's client router. -->
            <R10Button
                :href="authentikRedirect.url()"
                external
                variant="outline"
                class="w-full"
                data-test="authentik-login-link"
            >
                <LogIn class="h-4 w-4" />
                Jätka R10 kontoga (Planka)
            </R10Button>
        </template>
    </PasskeyVerify>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <R10Input
                id="email"
                v-model="email"
                type="email"
                name="email"
                label="E-post"
                required
                autofocus
                :tabindex="1"
                autocomplete="email"
                placeholder="email@example.com"
                :error="errors.email ?? linkForm.errors.email"
                @keydown.enter="submitOnEnter"
            />

            <!-- The two ways in, held back until the address is worth acting
                 on so the screen opens as a single field. -->
            <div
                v-if="emailLooksValid && !enteringPassword"
                class="grid gap-2"
                data-test="login-method-choice"
            >
                <R10Button
                    type="button"
                    size="lg"
                    class="w-full"
                    :tabindex="2"
                    :disabled="linkForm.processing"
                    data-test="send-login-link-button"
                    @click="sendLoginLink"
                >
                    <Spinner v-if="linkForm.processing" />
                    <Mail v-else class="h-4 w-4" />
                    Saada sisselogimislink
                </R10Button>

                <R10Button
                    type="button"
                    variant="outline"
                    size="lg"
                    class="w-full"
                    :tabindex="3"
                    data-test="enter-password-button"
                    @click="enterPassword"
                >
                    <Lock class="h-4 w-4" />
                    Sisesta parool
                </R10Button>

                <p class="mt-1 text-center text-xs text-r10-grey-500">
                    Sisselogimislink saadetakse e-postiga ja kehtib 30 minutit.
                </p>
            </div>

            <!-- Password entry, only once it has been asked for. -->
            <template v-if="enteringPassword">
                <div class="grid gap-2">
                    <PasswordInput
                        id="password"
                        ref="passwordField"
                        name="password"
                        label="Parool"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Parool"
                        :error="errors.password"
                    />
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Unustasid parooli?
                    </TextLink>
                </div>

                <div class="flex items-center justify-between">
                    <label
                        for="remember"
                        class="flex items-center space-x-3 text-sm text-r10-grey-700"
                    >
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Jäta mind meelde</span>
                    </label>
                </div>

                <R10Button
                    type="submit"
                    size="lg"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Logi sisse
                </R10Button>

                <button
                    type="button"
                    class="cursor-pointer text-center text-sm text-r10-grey-500 underline underline-offset-4 transition-colors hover:text-r10-orange"
                    :tabindex="5"
                    data-test="back-to-login-methods"
                    @click="enteringPassword = false"
                >
                    Vali muu sisselogimisviis
                </button>
            </template>
        </div>

        <div class="text-center text-sm text-r10-grey-500">
            Pole veel kontot?
            <TextLink
                :href="
                    register({
                        query: {
                            invitation: teamInvitation?.code,
                        },
                    })
                "
                :tabindex="5"
                data-test="register-link"
            >
                Registreeru
            </TextLink>
        </div>
    </Form>
</template>
