<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
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
const { items } = useProjectScopeNav();
</script>

<template>
    <div class="flex items-center gap-2 px-3.5 pt-3">
        <div class="flex flex-1 items-center gap-2 overflow-x-auto">
            <Link
                v-for="item in items"
                :key="item.id"
                :href="item.href"
                class="flex h-[30px] flex-none items-center gap-1.5 rounded-full border border-border px-3 text-[12.5px]"
                :class="
                    item.active
                        ? 'bg-accent font-semibold text-foreground'
                        : 'bg-transparent font-normal text-secondary-foreground'
                "
            >
                <span class="size-1.5 rounded-full" :class="item.dotClass" />
                {{ item.name }}
            </Link>
        </div>

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
