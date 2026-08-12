<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
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

watch(
    () => filters.q,
    (value) => {
        term.value = value ?? '';
    },
);

const statuses: PromptStatus[] = ['todo', 'implementing', 'done'];
const priorities: PromptPriority[] = ['high', 'normal', 'low'];
const tags = computed(() => page.props.tags);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Input
            v-model="term"
            type="search"
            placeholder="Search prompts…"
            class="max-w-xs"
            @input="emit('search', term)"
        />

        <div class="flex gap-1">
            <Badge
                v-for="status in statuses"
                :key="status"
                :variant="filters.status.includes(status) ? 'default' : 'outline'"
                class="cursor-pointer capitalize"
                @click="emit('toggleStatus', status)"
            >
                {{ status }}
            </Badge>
        </div>

        <div class="flex gap-1">
            <Badge
                v-for="priority in priorities"
                :key="priority"
                :variant="filters.priority.includes(priority) ? 'default' : 'outline'"
                class="cursor-pointer capitalize"
                @click="emit('togglePriority', priority)"
            >
                {{ priority }}
            </Badge>
        </div>

        <div v-if="tags.length > 0" class="flex flex-wrap gap-1">
            <Badge
                v-for="tag in tags"
                :key="tag"
                :variant="filters.tags.includes(tag) ? 'default' : 'outline'"
                class="cursor-pointer"
                @click="emit('toggleTag', tag)"
            >
                #{{ tag }}
            </Badge>
        </div>
    </div>
</template>
