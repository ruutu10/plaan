<script setup lang="ts">
import { PanelLeftClose, PanelLeftOpen } from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';

const { isMobile, state, toggleSidebar } = useSidebar();

const isCollapsed = computed(() => state.value === 'collapsed');
const label = computed(() =>
    isCollapsed.value ? 'Laienda paneel' : 'Peida paneel',
);
</script>

<template>
    <!-- On a phone the sidebar is a sheet that never folds down to icons, so
         there is nothing to collapse; the sheet closes on its own overlay. -->
    <SidebarGroup v-if="!isMobile" class="group-data-[collapsible=icon]:p-0">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem>
                    <!-- Dimmed like the reference links beside it in NavFooter. -->
                    <SidebarMenuButton
                        class="text-white/70"
                        :tooltip="label"
                        data-test="sidebar-collapse-toggle"
                        @click="toggleSidebar"
                    >
                        <PanelLeftOpen v-if="isCollapsed" />
                        <PanelLeftClose v-else />
                        <span>{{ label }}</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
