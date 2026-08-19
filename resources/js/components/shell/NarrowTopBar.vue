<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import { useProjectScopeNav } from '@/composables/useProjectScopeNav';
import { index } from '@/routes/prompts';

/**
 * Stands in for the sidebar below the shell's narrow breakpoint, where the
 * sidebar is hidden entirely. Every list pane renders this so that project
 * scope, capture and the account menu stay reachable on mobile.
 *
 * Pages that can open a draft in place handle `newPrompt`; pages that cannot
 * (settings) pass `linkToQueue` so the button navigates to the queue instead.
 */
const { linkToQueue = false } = defineProps<{ linkToQueue?: boolean }>();

const emit = defineEmits<{ newPrompt: [] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
const { items, allPromptsItem } = useProjectScopeNav();

/*
  The scope used to be a row of pills the user had to scroll sideways through,
  which hid every project past the third on a phone. One trigger naming the
  current scope replaces it, and the menu holds the rest.

  Off the queue nothing is active — `useProjectScopeNav` gates that
  deliberately — so the trigger names "All prompts", which is where tapping
  through lands.
*/
const activeItem = computed(
    () => items.value.find((item) => item.active) ?? allPromptsItem.value,
);
</script>

<template>
    <div class="flex items-center gap-2 px-3.5 pt-3">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    aria-label="Change project scope"
                    class="flex h-[30px] min-w-0 flex-1 items-center gap-1.5 rounded-full border border-border bg-accent px-3 text-[12.5px] font-semibold text-foreground"
                >
                    <span
                        class="size-1.5 flex-none rounded-full"
                        :class="activeItem.dotClass"
                    />
                    <span class="truncate">{{ activeItem.name }}</span>
                    <span class="ml-auto flex-none text-[8px] opacity-70"
                        >▾</span
                    >
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="w-60 border-ring bg-popover text-popover-foreground"
                align="start"
            >
                <DropdownMenuItem
                    v-for="item in items"
                    :key="item.id"
                    as-child
                    class="gap-2 focus:bg-surface-hover focus:text-foreground"
                >
                    <Link
                        :href="item.href"
                        class="flex w-full items-center gap-2 text-[13px]"
                        :class="
                            item.active
                                ? 'font-semibold text-foreground'
                                : 'font-normal text-muted-foreground'
                        "
                    >
                        <span
                            class="size-1.5 flex-none rounded-full"
                            :class="item.dotClass"
                        />
                        <span class="truncate">{{ item.name }}</span>
                        <span
                            v-if="item.count !== null"
                            class="ml-auto flex-none font-mono text-[11px] text-subtle-foreground"
                        >
                            {{ item.count }}
                        </span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <Link
            v-if="linkToQueue"
            :href="index()"
            aria-label="New prompt"
            class="flex size-[30px] flex-none items-center justify-center rounded-full bg-primary text-primary-foreground"
        >
            <Plus class="size-4" />
        </Link>
        <button
            v-else
            type="button"
            aria-label="New prompt"
            class="flex size-[30px] flex-none items-center justify-center rounded-full bg-primary text-primary-foreground"
            @click="emit('newPrompt')"
        >
            <Plus class="size-4" />
        </button>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    aria-label="Account menu"
                    class="flex size-[30px] flex-none items-center justify-center rounded-full bg-surface-hover text-[10px] font-bold text-secondary-foreground"
                >
                    {{ getInitials(user.name) }}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="w-56 border-ring bg-popover text-popover-foreground"
                align="end"
            >
                <UserMenuContent :user="user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
