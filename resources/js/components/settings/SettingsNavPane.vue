<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppPane from '@/components/shell/AppPane.vue';
import NarrowTopBar from '@/components/shell/NarrowTopBar.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/**
 * The settings equivalent of the prompt list pane — same column chrome, same
 * row treatment, so moving between the queue and settings never changes the
 * shape of the shell.
 */
const { narrow = false } = defineProps<{ narrow?: boolean }>();

const items: NavItem[] = [
    { title: 'Profile', href: editProfile() },
    { title: 'Security', href: editSecurity() },
    { title: 'Appearance', href: editAppearance() },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <AppPane variant="list" :narrow="narrow">
        <NarrowTopBar v-if="narrow" link-to-queue />

        <div class="flex flex-col gap-3 px-[18px] pt-[18px] pb-3">
            <div class="flex items-center gap-2.5">
                <span class="size-1.5 rounded-full bg-faint-foreground" />
                <h1 class="text-[19px] font-bold tracking-tight">Settings</h1>
            </div>
            <p class="text-[12.5px] leading-relaxed text-subtle-foreground">
                Manage your profile and account settings.
            </p>
        </div>

        <nav
            class="flex flex-1 flex-col gap-1 px-3 pb-3.5"
            aria-label="Settings"
        >
            <Link
                v-for="item in items"
                :key="toUrl(item.href)"
                :href="item.href"
                class="flex h-9 items-center gap-2.5 rounded-lg px-2.5 text-[13px] hover:bg-accent"
                :class="
                    isCurrentOrParentUrl(item.href)
                        ? 'bg-accent font-semibold text-foreground'
                        : 'font-normal text-secondary-foreground'
                "
            >
                {{ item.title }}
            </Link>
        </nav>
    </AppPane>
</template>
