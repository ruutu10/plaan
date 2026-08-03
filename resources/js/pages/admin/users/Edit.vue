<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { Check, Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import R10BackLink from '@/components/technical-plan/R10BackLink.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10SectionHeader from '@/components/technical-plan/R10SectionHeader.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { useResource } from '@/composables/useResource';
import { useTrailingCrumb } from '@/composables/useTrailingCrumb';
import { formatEstonianTimestamp } from '@/lib/date';
import { edit, index } from '@/routes/admin/users';
import { show as userApi, update } from '@/routes/api/users';
import {
    destroy as revokeRole,
    store as grantRole,
} from '@/routes/api/users/roles';
import type {
    ManagedRole,
    ManagedUser,
    ManagedUserFormData,
    ManagedUserPermissions,
} from '@/types';

const props = defineProps<{ userId: number }>();

/** Every role that could be granted, which only this page reads. */
const roles = ref<ManagedRole[]>([]);
/** What this reader may write; nothing at all until the account lands. */
const permissions = ref<ManagedUserPermissions>({
    canUpdateRoles: false,
});

/** The role a toggle is waiting on, so only that row's button goes quiet. */
const rolePending = ref<string | null>(null);

const loader = useHttp();

const form = useHttp<ManagedUserFormData>({ name: '', email: '' });

const roleForm = useHttp<{ role: string }>({ role: '' });

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

const nameTheTrail = useTrailingCrumb(
    { title: 'Kasutajad', href: index() },
    edit(props.userId),
);

const { data: user, loadFailed } = useResource(async () => {
    const response = (await loader.submit(userApi(props.userId))) as {
        data: ManagedUser;
        roles: ManagedRole[];
        permissions: ManagedUserPermissions;
    };

    roles.value = response.roles;
    permissions.value = response.permissions;

    form.name = response.data.name;
    form.email = response.data.email;
    form.defaults();

    nameTheTrail(response.data.name);

    return response.data;
});

/** The role slugs the account holds, for the toggles to key off. */
const heldRoles = computed(
    () => new Set((user.value?.roles ?? []).map((role) => role.name)),
);

async function save(): Promise<void> {
    try {
        const { data } = (await form.submit(update(props.userId))) as {
            data: ManagedUser;
        };

        user.value = data;
        form.name = data.name;
        form.email = data.email;
        form.defaults();

        nameTheTrail(data.name);

        toast.success('Kasutaja salvestatud.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Salvestamine ebaõnnestus. Proovi uuesti.');
        }
    }
}

/**
 * Grant or take away one role. The account is replaced with what the server
 * answers rather than with what was clicked, so a refused write leaves the
 * toggles showing what is actually held.
 */
async function toggleRole(role: ManagedRole): Promise<void> {
    const held = heldRoles.value.has(role.name);

    rolePending.value = role.name;

    try {
        roleForm.role = role.name;

        const { data } = (await (held
            ? loader.submit(revokeRole([props.userId, role.name]))
            : roleForm.submit(grantRole(props.userId)))) as {
            data: ManagedUser;
        };

        user.value = data;

        toast.success(held ? 'Roll eemaldatud.' : 'Roll antud.');
    } catch {
        toast.error('Rolli muutmine ebaõnnestus. Proovi uuesti.');
    } finally {
        rolePending.value = null;
    }
}
</script>

<template>
    <Head :title="user?.name ?? 'Kasutaja'" />

    <R10Page>
        <StepHeader
            eyebrow="Haldus"
            :title="user?.name ?? 'Kasutaja'"
            lead="Muuda konto andmeid ja seda, milliseid rolle see kannab."
        />

        <p
            v-if="loadFailed"
            class="max-w-2xl rounded-xl border-2 border-r10-grey-200 bg-white p-5 text-[15px] text-r10-orange-700 md:p-7"
        >
            Kasutaja laadimine ebaõnnestus. Proovi lehte värskendada.
        </p>

        <!-- Nothing has arrived yet: a stand-in the shape of the two fields. -->
        <div
            v-else-if="user === null"
            data-test="user-form-skeleton"
            class="flex max-w-2xl flex-col gap-4 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
        >
            <span
                class="block h-3 w-24 animate-pulse rounded-full bg-r10-grey-200"
            />
            <span class="block h-12 animate-pulse rounded-lg bg-r10-grey-100" />
            <span class="block h-12 animate-pulse rounded-lg bg-r10-grey-100" />
        </div>

        <template v-else>
            <!-- Anybody who got this far may correct the account: the screen is
                 behind the permission, and there is nothing finer to ask. -->
            <form
                class="flex max-w-2xl flex-col gap-6 rounded-xl border-2 border-r10-grey-200 bg-white p-5 md:p-7"
                @submit.prevent="save"
            >
                <R10Input
                    v-model="form.name"
                    label="Nimi"
                    required
                    placeholder="Kasutaja nimi"
                    :error="form.errors.name"
                    error-test-id="user-name-error"
                />

                <R10Input
                    v-model="form.email"
                    label="E-post"
                    type="email"
                    required
                    hint="Aadressi muutmine tühistab selle kinnituse — kasutaja peab uue aadressi uuesti kinnitama."
                    placeholder="nimi@näide.ee"
                    :error="form.errors.email"
                    error-test-id="user-email-error"
                />

                <div class="flex items-center gap-3">
                    <R10Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="user-save-button"
                    >
                        Salvesta
                    </R10Button>

                    <R10BackLink :href="index()" />
                </div>
            </form>

            <!-- What the account is, as against what may be typed into it. -->
            <dl
                class="mt-6 grid max-w-2xl grid-cols-1 gap-4 rounded-xl border-2 border-r10-grey-200 bg-white p-5 sm:grid-cols-3 md:p-7"
                data-test="user-details"
            >
                <div>
                    <dt
                        class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                    >
                        E-post
                    </dt>
                    <dd class="mt-1.5">
                        <R10Pill
                            :tone="user.emailVerified ? 'navy' : 'accent'"
                            data-test="user-verified-pill"
                        >
                            {{
                                user.emailVerified
                                    ? 'Kinnitatud'
                                    : 'Kinnitamata'
                            }}
                        </R10Pill>
                    </dd>
                </div>
                <div>
                    <dt
                        class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                    >
                        Liitus
                    </dt>
                    <dd class="mt-1.5 text-[15px] text-r10-ink">
                        {{ formatEstonianTimestamp(user.createdAt) }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-grey-500 uppercase"
                    >
                        Registreerus
                    </dt>
                    <dd class="mt-1.5 text-[15px] text-r10-ink">
                        {{ user.signupSourceLabel }}
                    </dd>
                </div>
            </dl>

            <section class="mt-9 max-w-2xl">
                <R10SectionHeader
                    title="Rollid"
                    lead="Roll annab õigused kogu majas. Oma konto rolle muuta ei saa — seda teeb teine tehnik."
                    class="mb-4"
                />

                <ul
                    class="flex flex-col rounded-xl border-2 border-r10-grey-200 bg-white"
                >
                    <li
                        v-for="role in roles"
                        :key="role.name"
                        data-test="user-role-row"
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-r10-grey-200 px-5 py-4 last:border-0"
                    >
                        <span class="flex items-center gap-2.5">
                            <Check
                                v-if="heldRoles.has(role.name)"
                                class="h-4 w-4 text-r10-navy"
                                data-test="user-role-held"
                            />
                            <span
                                class="font-medium"
                                :class="
                                    heldRoles.has(role.name)
                                        ? 'text-r10-ink'
                                        : 'pl-6.5 text-r10-grey-500'
                                "
                            >
                                {{ role.label }}
                            </span>
                        </span>

                        <R10Button
                            v-if="permissions.canUpdateRoles"
                            :variant="
                                heldRoles.has(role.name) ? 'outline' : 'primary'
                            "
                            size="sm"
                            :disabled="rolePending !== null"
                            :data-test="
                                heldRoles.has(role.name)
                                    ? 'revoke-role-button'
                                    : 'grant-role-button'
                            "
                            class="px-4 py-2"
                            @click="toggleRole(role)"
                        >
                            <template v-if="heldRoles.has(role.name)">
                                <X class="h-3.5 w-3.5" />
                                Eemalda
                            </template>
                            <template v-else>
                                <Plus class="h-3.5 w-3.5" />
                                Anna roll
                            </template>
                        </R10Button>
                    </li>

                    <li
                        v-if="roles.length === 0"
                        class="px-5 py-12 text-center text-[15px] text-r10-grey-500"
                    >
                        Ühtegi rolli pole loodud.
                    </li>
                </ul>
            </section>
        </template>
    </R10Page>
</template>
