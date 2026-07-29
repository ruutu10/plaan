<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import { edit } from '@/routes/security';

type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Turvalisuse seaded',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Turvalisuse seaded" />

    <h1 class="sr-only">Turvalisuse seaded</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Muuda parooli"
            description="Turvalisuse huvides kasuta pikka ja juhuslikku parooli"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <PasswordInput
                id="current_password"
                name="current_password"
                label="Praegune parool"
                autocomplete="current-password"
                placeholder="Praegune parool"
                :error="errors.current_password"
            />

            <PasswordInput
                id="password"
                name="password"
                label="Uus parool"
                autocomplete="new-password"
                placeholder="Uus parool"
                :passwordrules="props.passwordRules"
                :error="errors.password"
            />

            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                label="Kinnita parool"
                autocomplete="new-password"
                placeholder="Kinnita parool"
                :passwordrules="props.passwordRules"
                :error="errors.password_confirmation"
            />

            <div class="flex items-center gap-4">
                <R10Button
                    type="submit"
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Salvesta
                </R10Button>
            </div>
        </Form>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
