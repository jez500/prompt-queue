<script setup lang="ts">
/**
 * The bordered header row at the top of a detail pane.
 *
 * `eyebrow` renders the small uppercase mono label; the default slot carries
 * the leading controls and the `actions` slot is pushed to the trailing edge.
 * On narrow viewports a back button appears when `onBack` is bound, since the
 * list pane is hidden at that width.
 */
const { eyebrow, narrow = false } = defineProps<{
    eyebrow?: string;
    narrow?: boolean;
}>();

const emit = defineEmits<{ back: [] }>();
</script>

<template>
    <div
        class="flex min-h-[60px] flex-none flex-wrap items-center gap-2.5 border-b border-sidebar-border"
        :class="narrow ? 'px-3.5' : 'px-6'"
    >
        <button
            v-if="narrow"
            type="button"
            aria-label="Back to list"
            class="flex size-7 flex-none items-center justify-center rounded-lg bg-muted text-[15px] text-secondary-foreground"
            @click="emit('back')"
        >
            ‹
        </button>

        <div
            v-if="eyebrow"
            class="font-mono text-[11px] tracking-[0.08em] text-faint-foreground uppercase"
        >
            {{ eyebrow }}
        </div>

        <slot />

        <div class="flex-1" />

        <slot name="actions" />
    </div>
</template>
