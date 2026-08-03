<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { useResource } from '@/composables/useResource';
import { formatEstonianTimestamp } from '@/lib/date';
import { edit, index } from '@/routes/admin/users';
import { index as usersApi } from '@/routes/api/users';
import type { ManagedUser } from '@/types';

const http = useHttp();

const { data: users, loadFailed } = useResource(async () => {
    const response = (await http.submit(usersApi())) as {
        data: ManagedUser[];
    };

    return response.data;
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kasutajad',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Kasutajad" />

    <R10Page>
        <StepHeader
            eyebrow="Haldus"
            title="Kasutajad"
            lead="Kõik maja kontod. Ava konto, et muuta selle andmeid või rolle."
        />

        <R10Table
            :columns="[
                { label: 'Kasutaja' },
                { label: 'Rollid' },
                { label: 'Tiime' },
                { label: 'Liitunud' },
                { label: 'Tegevused', align: 'right', srOnly: true },
            ]"
            :rows="users"
            :load-failed="loadFailed"
            :skeleton-widths="['w-48', 'w-24', 'w-8', 'w-28']"
            row-test-id="user-row"
            skeleton-test-id="user-skeleton-row"
            empty-text="Ühtegi kontot pole veel loodud."
            error-text="Kasutajate laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: user }">
                <td class="px-5 py-4 align-top">
                    <span class="font-medium text-r10-ink">
                        {{ user.name }}
                    </span>
                    <span class="mt-0.5 block text-[13px] text-r10-grey-500">
                        {{ user.email }}
                    </span>
                    <!-- An unproven address is why an account may be missing
                         rights it looks like it should have. -->
                    <R10Pill
                        v-if="!user.emailVerified"
                        class="mt-1.5"
                        tone="accent"
                        data-test="user-unverified-pill"
                    >
                        E-post kinnitamata
                    </R10Pill>
                </td>
                <td class="px-5 py-4 align-top">
                    <div class="flex flex-wrap gap-1.5">
                        <R10Pill
                            v-for="role in user.roles"
                            :key="role.name"
                            tone="navy"
                            data-test="user-role-pill"
                        >
                            {{ role.label }}
                        </R10Pill>
                        <span
                            v-if="user.roles.length === 0"
                            class="text-[13px] text-r10-grey-500"
                        >
                            —
                        </span>
                    </div>
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap tabular-nums">
                    {{ user.teamCount ?? 0 }}
                </td>
                <td
                    class="px-5 py-4 align-top text-[13px] whitespace-nowrap text-r10-grey-500"
                >
                    {{ formatEstonianTimestamp(user.createdAt) }}
                </td>
                <td class="px-5 py-4 text-right align-top">
                    <R10Button
                        variant="outline"
                        size="sm"
                        :href="edit(user.id).url"
                        data-test="user-edit-link"
                        class="px-4 py-2"
                    >
                        Muuda
                        <Pencil class="h-3.5 w-3.5" />
                    </R10Button>
                </td>
            </template>
        </R10Table>
    </R10Page>
</template>
