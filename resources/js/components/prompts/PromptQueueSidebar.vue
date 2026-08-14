<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ProjectFormDialog from '@/components/projects/ProjectFormDialog.vue';
import ProjectScopeRow from '@/components/prompts/ProjectScopeRow.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import type { ProjectScopeItem } from '@/composables/useProjectScopeNav';
import { useProjectScopeNav } from '@/composables/useProjectScopeNav';
import { reorder } from '@/routes/projects';
import { index } from '@/routes/prompts';

/** Long enough to be deliberate, short enough that the cue explains the wait. */
const REORDER_DELAY = 800;

/** How long a drop keeps swallowing clicks, in ms. */
const CLICK_SUPPRESSION = 250;

const { collapsed } = defineProps<{ collapsed: boolean }>();

const emit = defineEmits<{ toggle: [] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
const { allPromptsItem, projectItems, inboxItem } = useProjectScopeNav();

const ordered = ref<ProjectScopeItem[]>([...projectItems.value]);

watch(projectItems, (value) => {
    ordered.value = [...value];
});

/*
  A drag finishes with a click on whatever row it dropped onto, which would
  otherwise navigate away the instant the user let go. Bounded by a timeout
  rather than the request, so a slow reorder cannot leave the rows unclickable.
*/
const justDragged = ref(false);

function suppressClosingClick(): void {
    justDragged.value = true;

    window.setTimeout(() => {
        justDragged.value = false;
    }, CLICK_SUPPRESSION);
}

function persist(): void {
    const snapshot = [...projectItems.value];

    suppressClosingClick();

    router.patch(
        reorder.url(),
        { ids: ordered.value.map((item) => Number(item.id)) },
        {
            preserveScroll: true,
            preserveState: true,
            /* A reorder is only ever visible in the project rows. */
            only: ['projects'],
            onError: () => {
                ordered.value = snapshot;
            },
        },
    );
}
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

            <ProjectScopeRow :item="allPromptsItem" :collapsed="collapsed" />

            <!-- Only the projects reorder: "All prompts" and the Inbox are
                 fixed ends of the list, not things the user arranged. -->
            <draggable
                v-model="ordered"
                item-key="id"
                :delay="REORDER_DELAY"
                :touch-start-threshold="10"
                :animation="150"
                ghost-class="opacity-40"
                class="flex flex-col gap-1.5"
                @end="persist"
            >
                <template #item="{ element }: { element: ProjectScopeItem }">
                    <ProjectScopeRow
                        :item="element"
                        :collapsed="collapsed"
                        :reorderable="true"
                        :suppress-navigation="justDragged"
                    />
                </template>
            </draggable>

            <ProjectScopeRow
                v-if="inboxItem"
                :item="inboxItem"
                :collapsed="collapsed"
            />
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
