<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store as storeInvitation } from '@/routes/teams/invitations';
import type { RoleOption, Team } from '@/types';

const props = defineProps<{ team: Team; availableRoles: RoleOption[] }>();

const open = defineModel<boolean>('open', { required: true });

const inviteRole = ref<string | number>('member');
/** Bumped to remount the form, which is how a closed dialog forgets what was typed. */
const formKey = ref(0);

const roleOptions = computed(() =>
    props.availableRoles.map((role) => ({
        value: role.value,
        label: role.label,
    })),
);

function change(value: boolean): void {
    open.value = value;

    if (!value) {
        inviteRole.value = 'member';
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="change">
        <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
            <Form
                :key="formKey"
                v-bind="storeInvitation.form(team.slug)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle
                        class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                    >
                        Kutsu liige
                    </DialogTitle>
                    <DialogDescription class="text-[15px] text-r10-grey-500">
                        Saada kutse tiimiga liitumiseks. Kutse kehtib
                        tähtajaliselt ja kutsutu saab selle kohta e-maili.
                    </DialogDescription>
                </DialogHeader>

                <R10Input
                    name="email"
                    type="email"
                    label="E-post"
                    required
                    placeholder="nimi@näide.ee"
                    data-test="invite-email"
                    :error="errors.email"
                />

                <R10Select
                    v-model="inviteRole"
                    name="role"
                    label="Roll"
                    required
                    :options="roleOptions"
                    :error="errors.role"
                    data-test="invite-role"
                />

                <div class="flex items-center justify-end gap-3">
                    <R10Button variant="outline" @click="change(false)">
                        Loobu
                    </R10Button>

                    <R10Button
                        type="submit"
                        data-test="invite-submit"
                        :disabled="processing"
                    >
                        Saada kutse
                    </R10Button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
