<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Notice from '@/components/technical-plan/R10Notice.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <R10Notice
        v-if="status === 'verification-link-sent'"
        tone="success"
        class="mb-4"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </R10Notice>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <R10Button type="submit" variant="outline" :disabled="processing">
            <Spinner v-if="processing" />
            Resend verification email
        </R10Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
