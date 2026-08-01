<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/roles';
import type { Team } from '@/types';

defineProps<{
    roles: string[];
    permissions: string[];
    teams: Team[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Rollid ja õigused',
                href: show(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Rollid ja õigused" />

    <h1 class="sr-only">Rollid ja õigused</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            title="Minu rollid ja õigused"
            description="Vaata, millised rollid ja õigused on sinu kontoga seotud"
        />

        <section class="space-y-3" data-test="global-roles-section">
            <h2 class="text-sm font-medium">Rollid</h2>

            <div v-if="roles.length > 0" class="flex flex-wrap gap-2">
                <Badge
                    v-for="role in roles"
                    :key="role"
                    variant="secondary"
                    data-test="global-role"
                >
                    {{ role }}
                </Badge>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Sul pole ühtegi rolli määratud.
            </p>
        </section>

        <section class="space-y-3" data-test="global-permissions-section">
            <h2 class="text-sm font-medium">Õigused</h2>

            <div v-if="permissions.length > 0" class="flex flex-wrap gap-2">
                <Badge
                    v-for="permission in permissions"
                    :key="permission"
                    variant="outline"
                    data-test="global-permission"
                >
                    {{ permission }}
                </Badge>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Sul pole ühtegi eriõigust.
            </p>
        </section>

        <section class="space-y-3" data-test="team-roles-section">
            <h2 class="text-sm font-medium">Rollid tiimides</h2>

            <ul
                v-if="teams.length > 0"
                class="divide-y divide-border rounded-lg border"
            >
                <li
                    v-for="team in teams"
                    :key="team.id"
                    class="flex items-center justify-between gap-4 px-4 py-3"
                    :data-test="`team-role-${team.slug}`"
                >
                    <span class="font-medium">{{ team.name }}</span>
                    <Badge variant="secondary">{{ team.roleLabel }}</Badge>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Sa ei kuulu veel ühtegi tiimi.
            </p>
        </section>
    </div>
</template>
