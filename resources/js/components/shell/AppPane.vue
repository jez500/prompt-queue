<script setup lang="ts">
import { useShellBreakpoints } from '@/composables/useShellBreakpoints';

/**
 * The column chrome shared by every pane in the app shell.
 *
 * `list` is the fixed-width left column (prompt list, settings nav); `detail`
 * is the flexible right column that fills the remaining space. Below the
 * shell's narrow breakpoint both collapse to a single full-width column, which
 * is what lets a page swap between them on mobile. In the compact band the
 * list column sheds ~20% of its width to keep the detail header off two lines.
 */
const { variant, narrow = false } = defineProps<{
    variant: 'list' | 'detail';
    narrow?: boolean;
}>();

const { compact } = useShellBreakpoints();
</script>

<template>
    <section
        class="flex min-w-0 flex-col"
        :class="[
            variant === 'list' ? 'bg-pane' : 'flex-1 bg-background',
            narrow
                ? 'w-full flex-1'
                : variant === 'list'
                  ? `${compact ? 'w-[344px]' : 'w-[430px]'} flex-none border-r border-sidebar-border`
                  : '',
        ]"
    >
        <slot />
    </section>
</template>
