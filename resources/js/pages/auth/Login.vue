<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { LogIn } from '@lucide/vue';
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
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: {
        title: 'Logi sisse',
        description: 'Sisesta oma e-post ja parool, et sisse logida',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    ssoEnabled: boolean;
    teamInvitation?: TeamInvitationContext | null;
}>();
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
                type="email"
                name="email"
                label="E-post"
                required
                autofocus
                :tabindex="1"
                autocomplete="email"
                placeholder="email@example.com"
                :error="errors.email"
            />

            <div class="grid gap-2">
                <PasswordInput
                    id="password"
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
