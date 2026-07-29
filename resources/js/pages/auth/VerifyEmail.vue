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
        title: 'Kinnita e-post',
        description:
            'Palun kinnita oma e-posti aadress, klõpsates lingil, mille saatsime sulle just.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Kinnita e-post" />

    <R10Notice
        v-if="status === 'verification-link-sent'"
        tone="success"
        class="mb-4"
    >
        Uus kinnituslink on saadetud e-posti aadressile, mille registreerimisel
        sisestasid.
    </R10Notice>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <R10Button type="submit" variant="outline" :disabled="processing">
            <Spinner v-if="processing" />
            Saada kinnituskiri uuesti
        </R10Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Logi välja
        </TextLink>
    </Form>
</template>
