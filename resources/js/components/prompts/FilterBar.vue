<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import { shortcutHint } from '@/composables/useKeyboardShortcuts';
import {
    PROMPT_PRIORITY_LABELS,
    PROMPT_PRIORITY_QUEUE_PILL_CLASSES,
} from '@/lib/promptPriority';
import {
    PROMPT_STATUS_LABELS,
    PROMPT_STATUS_QUEUE_PILL_CLASSES,
} from '@/lib/promptStatus';
import type { PromptFilters, PromptPriority, PromptStatus } from '@/types';

const { filters } = defineProps<{ filters: PromptFilters }>();

const emit = defineEmits<{
    search: [term: string];
    toggleStatus: [status: PromptStatus];
    togglePriority: [priority: PromptPriority];
    toggleTag: [tag: string];
}>();

const page = usePage();
const term = ref(filters.q ?? '');
const searchFocused = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);

/* Focused by the search shortcut, signalled from the shell. */
const searchSignal = inject<Ref<number>>('promptQueueSearch');

watch(
    () => searchSignal?.value,
    () => {
        searchInput.value?.focus();
        searchInput.value?.select();
    },
);

watch(
    () => filters.q,
    (value) => {
        term.value = value ?? '';
    },
);

const statuses: PromptStatus[] = ['todo', 'implementing', 'done'];
const priorities: PromptPriority[] = ['high', 'normal', 'low'];
const tags = computed(() => page.props.tags);

const inactivePillClass =
    'border border-border bg-transparent text-muted-foreground hover:border-border-hover hover:text-foreground';
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <div class="relative w-full">
            <input
                ref="searchInput"
                v-model="term"
                type="search"
                placeholder="Search prompts…"
                class="h-9 w-full rounded-xl border border-border bg-transparent pr-11 pl-3.5 font-sans text-sm text-foreground outline-none placeholder:text-ghost-foreground focus:border-border-hover"
                @input="emit('search', term)"
                @focus="searchFocused = true"
                @blur="searchFocused = false"
            />
            <span
                v-if="!term && !searchFocused"
                class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 font-mono text-[10.5px] text-faint-foreground"
            >
                {{ shortcutHint('search') }}
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="status in statuses"
                    :key="status"
                    type="button"
                    class="flex h-[27px] items-center rounded-full px-2.5 text-xs font-medium"
                    :class="
                        filters.status.includes(status)
                            ? PROMPT_STATUS_QUEUE_PILL_CLASSES[status]
                            : inactivePillClass
                    "
                    @click="emit('toggleStatus', status)"
                >
                    {{ PROMPT_STATUS_LABELS[status] }}
                </button>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="priority in priorities"
                    :key="priority"
                    type="button"
                    class="flex h-[27px] items-center rounded-full px-2.5 text-xs font-medium"
                    :class="
                        filters.priority.includes(priority)
                            ? PROMPT_PRIORITY_QUEUE_PILL_CLASSES[priority]
                            : inactivePillClass
                    "
                    @click="emit('togglePriority', priority)"
                >
                    {{ PROMPT_PRIORITY_LABELS[priority] }}
                </button>
            </div>

            <div v-if="tags.length > 0" class="flex flex-wrap gap-1.5">
                <button
                    v-for="tag in tags"
                    :key="tag"
                    type="button"
                    class="flex h-[27px] items-center rounded-full px-2.5 text-xs font-medium"
                    :class="
                        filters.tags.includes(tag)
                            ? 'border border-primary/60 bg-primary/15 text-primary-soft'
                            : inactivePillClass
                    "
                    @click="emit('toggleTag', tag)"
                >
                    #{{ tag }}
                </button>
            </div>
        </div>
    </div>
</template>
