<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Inbox, Layers } from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import { index } from '@/routes/prompts';

const page = usePage();

const projects = computed(() => page.props.projects);

const currentProject = computed<string | null>(() => {
    const value = new URL(page.url, 'http://localhost').searchParams.get(
        'project',
    );

    return value;
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Prompts</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    as-child
                    :is-active="currentProject === null"
                    tooltip="All prompts"
                >
                    <Link :href="index()">
                        <Layers />
                        <span>All</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton
                    as-child
                    :is-active="currentProject === 'inbox'"
                    tooltip="Inbox"
                >
                    <Link :href="index({ query: { project: 'inbox' } })">
                        <Inbox />
                        <span>Inbox</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem v-for="project in projects" :key="project.id">
                <SidebarMenuButton
                    as-child
                    :is-active="currentProject === String(project.id)"
                    :tooltip="project.name"
                >
                    <Link
                        :href="index({ query: { project: String(project.id) } })"
                    >
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :class="PROJECT_DOT_CLASSES[project.color]"
                        />
                        <span class="truncate">{{ project.name }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge v-if="project.openPromptsCount > 0">
                    {{ project.openPromptsCount }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
