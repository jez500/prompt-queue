<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ProjectFormDialog from '@/components/projects/ProjectFormDialog.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import { useProjectScopeNav } from '@/composables/useProjectScopeNav';
import {
    PROJECT_BORDER_CLASSES,
    PROJECT_TEXT_CLASSES,
} from '@/lib/projectColors';
import { index } from '@/routes/prompts';

const { collapsed } = defineProps<{ collapsed: boolean }>();

const emit = defineEmits<{ toggle: [] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
const { items } = useProjectScopeNav();
</script>

<template>
    <aside
        class="flex flex-none flex-col border-r border-sidebar-border bg-sidebar transition-[width] duration-150"
        :class="collapsed ? 'w-[68px] gap-3 p-2' : 'w-[212px] gap-[18px] p-2.5'"
    >
        <div
            class="flex items-center gap-2.5 px-1"
            :class="collapsed ? 'justify-center' : 'justify-between'"
        >
            <Link
                :href="index()"
                title="Prompt Queue"
                class="flex min-w-0 flex-1 items-center gap-2.5"
                :class="collapsed ? 'flex-none' : ''"
            >
                <span
                    class="flex size-[26px] flex-none items-center justify-center rounded-lg bg-primary"
                >
                    <AppLogoIcon class="size-3 text-background" />
                </span>
                <span
                    v-if="!collapsed"
                    class="min-w-0 flex-1 truncate text-sm font-bold tracking-tight text-foreground"
                >
                    Prompt Queue
                </span>
            </Link>
            <button
                v-if="!collapsed"
                type="button"
                title="Collapse sidebar"
                class="flex size-[26px] flex-none items-center justify-center rounded-[7px] border border-surface-hover bg-muted text-[13px] text-muted-foreground hover:border-border-hover hover:bg-accent hover:text-foreground"
                @click="emit('toggle')"
            >
                «
            </button>
        </div>

        <div v-if="collapsed" class="flex justify-center">
            <button
                type="button"
                title="Expand sidebar"
                class="flex size-9 items-center justify-center rounded-[10px] border border-border bg-card text-[13px] text-subtle-foreground hover:border-ring hover:text-foreground"
                @click="emit('toggle')"
            >
                »
            </button>
        </div>

        <!-- Capture lives on the list pane header, next to the list it adds
             to. The N shortcut still works from anywhere. -->
        <div class="mt-2 flex flex-col gap-1.5">
            <div
                v-if="!collapsed"
                class="flex items-center justify-between px-2.5"
            >
                <span
                    class="font-mono text-[10px] tracking-[0.12em] text-subtle-foreground uppercase"
                >
                    Projects
                </span>
                <ProjectFormDialog>
                    <button
                        type="button"
                        aria-label="New project"
                        class="text-[15px] leading-none text-subtle-foreground hover:text-foreground"
                    >
                        +
                    </button>
                </ProjectFormDialog>
            </div>

            <Link
                v-for="item in items"
                :key="item.id"
                :href="item.href"
                :title="item.name"
                class="flex items-center gap-2.5 rounded-lg hover:bg-popover"
                :class="[
                    collapsed
                        ? 'h-10 justify-center'
                        : 'h-8 justify-start px-2.5',
                    !collapsed && item.active ? 'bg-accent' : '',
                ]"
            >
                <div
                    v-if="collapsed"
                    class="flex size-9 flex-none items-center justify-center rounded-[10px] border-2 text-[11px] font-bold tracking-wide"
                    :class="[
                        item.active ? 'bg-accent' : 'bg-card',
                        item.active && item.color
                            ? PROJECT_BORDER_CLASSES[item.color]
                            : item.active
                              ? 'border-faint-foreground'
                              : 'border-border',
                        item.active && item.color
                            ? PROJECT_TEXT_CLASSES[item.color]
                            : item.active
                              ? 'text-faint-foreground'
                              : 'text-muted-foreground',
                    ]"
                >
                    {{ item.initials }}
                </div>
                <span
                    v-else
                    class="size-1.5 flex-none rounded-full"
                    :class="item.dotClass"
                />
                <span
                    v-if="!collapsed"
                    class="min-w-0 flex-1 truncate text-[13px]"
                    :class="
                        item.active
                            ? 'font-semibold text-foreground'
                            : 'font-normal text-secondary-foreground'
                    "
                >
                    {{ item.name }}
                </span>
                <span
                    v-if="!collapsed && item.count !== null"
                    class="font-mono text-[11px] text-subtle-foreground"
                >
                    {{ item.count }}
                </span>
            </Link>
        </div>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="mt-auto flex h-[38px] items-center gap-2.5 rounded-[9px] hover:bg-popover"
                    :class="
                        collapsed ? 'justify-center' : 'justify-start px-2.5'
                    "
                >
                    <span
                        class="flex size-6 flex-none items-center justify-center rounded-full bg-surface-hover text-[10px] font-bold text-secondary-foreground"
                    >
                        {{ getInitials(user.name) }}
                    </span>
                    <span
                        v-if="!collapsed"
                        class="flex-1 truncate text-left text-[13px] text-secondary-foreground"
                    >
                        {{ user.name }}
                    </span>
                    <ChevronsUpDown
                        v-if="!collapsed"
                        class="size-3 flex-none text-faint-foreground"
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="w-56 border-ring bg-popover text-popover-foreground"
                side="top"
                align="start"
            >
                <UserMenuContent :user="user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </aside>
</template>
