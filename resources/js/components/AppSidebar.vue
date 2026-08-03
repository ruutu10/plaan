<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleQuestionMark,
    ClipboardList,
    FolderGit2,
    LayoutGrid,
    ListPlus,
    Theater,
    UserCog,
    Users,
} from '@lucide/vue';
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
import { dashboard, manual } from '@/routes';
import { index as adminPerformances } from '@/routes/admin/performances';
import { index as adminTeams } from '@/routes/admin/teams';
import { index as adminUsers } from '@/routes/admin/users';
import { index as shows } from '@/routes/shows';
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
    // Everyone manages their own groups' shows; the list is empty rather than
    // shut for a user whose groups have staged nothing yet.
    {
        title: 'Lavastused',
        href: shows().url,
        icon: Theater,
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
    // The house-wide performance overview, next to the plans it feeds. A user
    // without the permission reaches their own groups' dates through Lavastused
    // and is not shown a door the server would shut.
    ...(page.props.auth?.can?.manageAllPerformances
        ? [
              {
                  title: 'Etendused',
                  href: adminPerformances().url,
                  icon: CalendarDays,
              },
          ]
        : []),
    // Likewise the group overview: everybody keeps their own teams straight
    // under Seaded, the crew keeps the whole house's.
    ...(page.props.auth?.can?.manageAllTeams
        ? [
              {
                  title: 'Tiimid',
                  href: adminTeams().url,
                  icon: Users,
              },
          ]
        : []),
    // The accounts themselves, and the roles they hold. Last of the management
    // doors and the narrowest: only the technicians hand out rights.
    ...(page.props.auth?.can?.manageUsers
        ? [
              {
                  title: 'Kasutajad',
                  href: adminUsers().url,
                  icon: UserCog,
              },
          ]
        : []),
]);

// Reference links, opened in a tab of their own by NavFooter — the manual is
// read alongside the screen it explains, not instead of it.
const footerNavItems: NavItem[] = [
    {
        title: 'Abi',
        href: manual().url,
        icon: CircleQuestionMark,
    },
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
