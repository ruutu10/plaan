<script setup lang="ts">
import { KeyRound, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Dialog from '@/components/technical-plan/R10Dialog.vue';
import type { Passkey } from '@/types/auth';

const props = defineProps<{
    passkey: Passkey;
}>();

const emit = defineEmits<{
    remove: [id: number, onError: () => void];
}>();

const isDeleting = ref(false);
const confirmOpen = ref(false);

const handleDelete = () => {
    isDeleting.value = true;
    emit('remove', props.passkey.id, () => {
        isDeleting.value = false;
    });
};
</script>

<template>
    <div class="flex items-center justify-between border-b p-4 last:border-b-0">
        <div class="flex items-center gap-4">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted"
            >
                <KeyRound class="h-5 w-5 text-muted-foreground" />
            </div>
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <p class="font-medium tracking-tight">{{ passkey.name }}</p>
                    <span
                        v-if="passkey.authenticator"
                        class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset"
                    >
                        {{ passkey.authenticator }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    Lisatud {{ passkey.created_at_diff }}
                    <template v-if="passkey.last_used_at_diff">
                        <span class="mx-1 text-muted-foreground/50">/</span>
                        Viimati kasutatud {{ passkey.last_used_at_diff }}
                    </template>
                </p>
            </div>
        </div>

        <button
            type="button"
            title="Eemalda pääsuvõti"
            class="inline-flex cursor-pointer items-center justify-center rounded-full border-2 border-r10-grey-200 bg-white p-2 text-r10-grey-500 transition hover:border-r10-error hover:text-r10-error"
            @click="confirmOpen = true"
        >
            <Trash2 class="h-3.5 w-3.5" />
            <span class="sr-only">Eemalda</span>
        </button>

        <R10Dialog v-model:open="confirmOpen" title="Eemalda pääsuvõti">
            <template #description>
                Kas soovid kindlasti eemaldada pääsuvõtme „{{ passkey.name }}“?
                Sa ei saa seda enam sisselogimiseks kasutada.
            </template>

            <template #actions>
                <R10Button variant="outline" @click="confirmOpen = false">
                    Loobu
                </R10Button>
                <R10Button
                    variant="danger"
                    :disabled="isDeleting"
                    @click="handleDelete"
                >
                    {{ isDeleting ? 'Eemaldan...' : 'Eemalda pääsuvõti' }}
                </R10Button>
            </template>
        </R10Dialog>
    </div>
</template>
