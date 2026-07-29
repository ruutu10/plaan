<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const passwordInput = useTemplateRef('passwordInput');
const open = ref(false);
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Delete account"
            description="Delete your account and all of its resources"
        />

        <div
            class="space-y-4 rounded-lg border-2 border-r10-orange bg-r10-orange-100 p-4"
        >
            <div class="flex gap-3 text-r10-grey-700">
                <TriangleAlert
                    class="mt-0.5 h-5 w-5 shrink-0 text-r10-orange-700"
                />
                <div class="space-y-0.5">
                    <p class="font-bold text-r10-ink">Warning</p>
                    <p class="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>
            </div>

            <R10Button
                variant="danger"
                data-test="delete-user-button"
                @click="open = true"
            >
                Delete account
            </R10Button>
        </div>

        <Dialog :open="open" @update:open="open = $event">
            <DialogContent class="bg-r10-paper font-r10-body text-r10-grey-700">
                <Form
                    v-bind="ProfileController.destroy.form()"
                    reset-on-success
                    @error="() => passwordInput?.focus()"
                    :options="{
                        preserveScroll: true,
                    }"
                    class="flex flex-col gap-6"
                    v-slot="{ errors, processing, reset, clearErrors }"
                >
                    <DialogHeader>
                        <DialogTitle
                            class="font-r10-display text-xl font-bold tracking-[0.02em] text-r10-ink uppercase"
                        >
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription
                            class="text-[15px] text-r10-grey-500"
                        >
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted. Please
                            enter your password to confirm you would like to
                            permanently delete your account.
                        </DialogDescription>
                    </DialogHeader>

                    <PasswordInput
                        id="password"
                        name="password"
                        ref="passwordInput"
                        label="Password"
                        placeholder="Password"
                        :error="errors.password"
                    />

                    <div class="flex items-center justify-end gap-3">
                        <R10Button
                            variant="outline"
                            @click="
                                () => {
                                    clearErrors();
                                    reset();
                                    open = false;
                                }
                            "
                        >
                            Cancel
                        </R10Button>

                        <R10Button
                            type="submit"
                            variant="danger"
                            :disabled="processing"
                            data-test="confirm-delete-user-button"
                        >
                            Delete account
                        </R10Button>
                    </div>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
