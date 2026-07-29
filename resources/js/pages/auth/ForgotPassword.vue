<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Notice from '@/components/technical-plan/R10Notice.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Unustasid parooli?',
        description: 'Sisesta oma e-post, et saada parooli lähtestamise link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Unustasid parooli?" />

    <R10Notice v-if="status" tone="success" class="mb-4">
        {{ status }}
    </R10Notice>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <R10Input
                id="email"
                type="email"
                name="email"
                label="E-post"
                autocomplete="off"
                autofocus
                placeholder="email@example.com"
                :error="errors.email"
            />

            <div class="my-6 flex items-center justify-start">
                <R10Button
                    type="submit"
                    size="lg"
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Saada parooli lähtestamise link
                </R10Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-r10-grey-500">
            <span>Või mine tagasi</span>
            <TextLink :href="login()">sisselogimise juurde</TextLink>
        </div>
    </div>
</template>
