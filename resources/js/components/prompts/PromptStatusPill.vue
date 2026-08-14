<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    PROMPT_STATUS_LABELS,
    PROMPT_STATUS_QUEUE_DOT_CLASSES,
    PROMPT_STATUS_QUEUE_PILL_CLASSES,
    PROMPT_STATUSES,
} from '@/lib/promptStatus';
import type { PromptStatus } from '@/types';

/**
 * The status pill and its menu, shared by the queue card and the detail pane.
 *
 * Presentation only: it reports the chosen status and lets the caller decide
 * what to persist, because the card patches immediately while the detail pane
 * routes through the autosave composable.
 */
const { modelValue, size = 'md' } = defineProps<{
    modelValue: PromptStatus;
    size?: 'sm' | 'md';
    align?: 'start' | 'end';
}>();

const emit = defineEmits<{ 'update:modelValue': [status: PromptStatus] }>();

const select = (value: AcceptableValue): void => {
    if (typeof value === 'string' && value !== modelValue) {
        emit('update:modelValue', value as PromptStatus);
    }
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-md font-mono tracking-[0.06em] uppercase"
                :class="[
                    PROMPT_STATUS_QUEUE_PILL_CLASSES[modelValue],
                    size === 'sm'
                        ? 'h-5 px-2 text-[10px]'
                        : 'h-[23px] px-2.5 text-[10.5px]',
                ]"
                @click.stop
            >
                <span>{{ PROMPT_STATUS_LABELS[modelValue] }}</span>
                <span class="text-[8px] opacity-70">▾</span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            :align="align ?? 'start'"
            class="w-44 border-ring bg-popover text-popover-foreground"
        >
            <DropdownMenuRadioGroup
                :model-value="modelValue"
                @update:model-value="select"
            >
                <DropdownMenuRadioItem
                    v-for="status in PROMPT_STATUSES"
                    :key="status"
                    :value="status"
                    class="gap-2 pl-2 text-muted-foreground focus:bg-surface-hover focus:text-foreground data-[state=checked]:text-foreground"
                >
                    <!-- The status colour is the marker; the menu's own
                         checked dot would sit beside it as a second one. -->
                    <template #indicator-icon><span /></template>
                    <span
                        class="size-1.5 rounded-full"
                        :class="PROMPT_STATUS_QUEUE_DOT_CLASSES[status]"
                    />
                    {{ PROMPT_STATUS_LABELS[status] }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
