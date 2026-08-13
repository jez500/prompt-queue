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
    PROMPT_PRIORITIES,
    PROMPT_PRIORITY_LABELS,
    PROMPT_PRIORITY_QUEUE_PILL_CLASSES,
} from '@/lib/promptPriority';
import type { PromptPriority } from '@/types';

/**
 * The priority pill and its menu, shared by the queue card and the detail
 * pane. Presentation only — see PromptStatusPill for why.
 */
const { modelValue, size = 'md' } = defineProps<{
    modelValue: PromptPriority;
    size?: 'sm' | 'md';
    align?: 'start' | 'end';
}>();

const emit = defineEmits<{ 'update:modelValue': [priority: PromptPriority] }>();

const select = (value: AcceptableValue): void => {
    if (typeof value === 'string' && value !== modelValue) {
        emit('update:modelValue', value as PromptPriority);
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
                    PROMPT_PRIORITY_QUEUE_PILL_CLASSES[modelValue],
                    size === 'sm'
                        ? 'h-5 px-2 text-[10px]'
                        : 'h-[23px] px-2.5 text-[10.5px]',
                ]"
                @click.stop
            >
                <span>{{ PROMPT_PRIORITY_LABELS[modelValue] }}</span>
                <span class="text-[8px] opacity-70">▾</span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            :align="align ?? 'start'"
            class="w-36 border-ring bg-popover text-popover-foreground"
        >
            <DropdownMenuRadioGroup
                :model-value="modelValue"
                @update:model-value="select"
            >
                <DropdownMenuRadioItem
                    v-for="priority in PROMPT_PRIORITIES"
                    :key="priority"
                    :value="priority"
                    class="focus:bg-surface-hover focus:text-foreground"
                >
                    {{ PROMPT_PRIORITY_LABELS[priority] }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
