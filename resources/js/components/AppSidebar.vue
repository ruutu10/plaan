<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ClipboardList, FolderGit2, LayoutGrid, ListPlus } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as technicalPlan } from '@/routes/technical-plan';
import { index as technicalPlans } from '@/routes/technical-plans';
import type { NavItem } from '@/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Töölaud',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    {
        title: 'Uus tehnikaplaan',
        href: technicalPlan().url,
        icon: ListPlus,
    },
    // The plan overview belongs to the technical crew; everyone else is not
    // shown a door the server would shut anyway.
    ...(page.props.auth?.can?.viewAllTechnicalPlans
        ? [
              {
                  title: 'Saadetud plaanid',
                  href: technicalPlans().url,
                  icon: ClipboardList,
              },
          ]
        : []),
]);

const footerNavItems: NavItem[] = [
    {
        title: 'GitHub',
        href: 'https://github.com/ruutu10/plaan',
        icon: FolderGit2,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
