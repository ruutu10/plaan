<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Notice from '@/components/technical-plan/R10Notice.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <R10Input
                id="name"
                name="name"
                label="Name"
                :default-value="user.name"
                required
                autocomplete="name"
                placeholder="Full name"
                :error="errors.name"
            />

            <R10Input
                id="email"
                type="email"
                name="email"
                label="Email address"
                :default-value="user.email"
                required
                autocomplete="username"
                placeholder="Email address"
                :error="errors.email"
            />

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-r10-grey-500">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="cursor-pointer font-medium text-r10-orange underline underline-offset-4 transition-colors hover:text-r10-orange-700"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <R10Notice
                    v-if="page.props.status === 'verification-link-sent'"
                    tone="success"
                    class="mt-2"
                >
                    A new verification link has been sent to your email address.
                </R10Notice>
            </div>

            <div class="flex items-center gap-4">
                <R10Button
                    type="submit"
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    Save
                </R10Button>
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
