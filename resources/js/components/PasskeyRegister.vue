<script setup lang="ts">
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';

const emit = defineEmits<{
    success: [];
}>();

const getDefaultPasskeyName = () => {
    const ua = navigator.userAgent;

    const browser = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'].find(
        (browser) => new RegExp(browser).test(ua),
    );

    const os = ['iPhone', 'iPad', 'Android', 'Mac', 'Windows'].find((os) =>
        new RegExp(os).test(ua),
    );

    return [browser, os].filter(Boolean).join(' on ') || '';
};

const name = ref(getDefaultPasskeyName());
const showForm = ref(false);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        emit('success');
    },
});

const handleSubmit = async (event: Event) => {
    event.preventDefault();

    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};

const handleCancel = () => {
    showForm.value = false;
    name.value = '';
};
</script>

<template>
    <div v-if="!isSupported" class="text-sm text-muted-foreground">
        Pääsuvõtmed ei ole selles brauseris toetatud.
    </div>

    <R10Button v-else-if="!showForm" variant="outline" @click="showForm = true">
        Lisa pääsuvõti
    </R10Button>

    <form
        v-else
        @submit="handleSubmit"
        class="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
    >
        <R10Input
            id="passkey-name"
            v-model="name"
            label="Pääsuvõtme nimi"
            hint="Nimi aitab sul seda pääsuvõtit hiljem ära tunda."
            placeholder="nt MacBook Pro, iPhone"
            autofocus
        />

        <InputError v-if="error" :message="error" />

        <div class="flex gap-2">
            <R10Button type="submit" :disabled="isLoading || !name.trim()">
                {{ isLoading ? 'Registreerin...' : 'Registreeri pääsuvõti' }}
            </R10Button>
            <R10Button type="button" variant="outline" @click="handleCancel">
                Loobu
            </R10Button>
        </div>
    </form>
</template>
