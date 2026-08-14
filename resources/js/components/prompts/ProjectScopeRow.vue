<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { ProjectScopeItem } from '@/composables/useProjectScopeNav';
import {
    PROJECT_BORDER_CLASSES,
    PROJECT_TEXT_CLASSES,
} from '@/lib/projectColors';

/** How far a pointer may wander before the press counts as a scroll, not a hold. */
const MOVE_TOLERANCE = 10;

const {
    item,
    collapsed,
    reorderable = false,
    suppressNavigation = false,
} = defineProps<{
    item: ProjectScopeItem;
    collapsed: boolean;
    reorderable?: boolean;
    suppressNavigation?: boolean;
}>();

/*
  Sortable only tells us a drag started once the delay has already elapsed,
  which is the end of the wait rather than the wait itself. So the row runs its
  own press timer purely for the cue: the class lands on pointerdown and its
  800ms transition finishes about when the drag actually takes hold.
*/
const pressing = ref(false);
const origin = ref<{ x: number; y: number } | null>(null);

function startPress(event: PointerEvent): void {
    if (!reorderable) {
        return;
    }

    origin.value = { x: event.clientX, y: event.clientY };
    pressing.value = true;
}

function trackPress(event: PointerEvent): void {
    if (!pressing.value || !origin.value) {
        return;
    }

    const travelled = Math.hypot(
        event.clientX - origin.value.x,
        event.clientY - origin.value.y,
    );

    if (travelled > MOVE_TOLERANCE) {
        endPress();
    }
}

function endPress(): void {
    pressing.value = false;
    origin.value = null;
}

/**
 * A drag ends with a click on the row it dropped onto, which would otherwise
 * navigate away the moment the user finishes rearranging.
 */
function onClick(event: MouseEvent): void {
    if (suppressNavigation) {
        event.preventDefault();
        event.stopPropagation();
    }
}
</script>

<template>
    <Link
        :href="item.href"
        :title="item.name"
        class="flex items-center gap-2.5 rounded-lg transition-[transform,background-color] hover:bg-popover"
        :class="[
            collapsed ? 'h-10 justify-center' : 'h-8 justify-start px-2.5',
            !collapsed && item.active ? 'bg-accent' : '',
            pressing
                ? 'scale-[0.97] bg-accent duration-[800ms]'
                : 'duration-150',
        ]"
        @pointerdown="startPress"
        @pointermove="trackPress"
        @pointerup="endPress"
        @pointercancel="endPress"
        @click.capture="onClick"
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
</template>
