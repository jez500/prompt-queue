<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FilterBar from '@/components/prompts/FilterBar.vue';
import PromptEditSheet from '@/components/prompts/PromptEditSheet.vue';
import PromptList from '@/components/prompts/PromptList.vue';
import QuickCapture from '@/components/prompts/QuickCapture.vue';
import { usePromptFilters } from '@/composables/usePromptFilters';
import { index } from '@/routes/prompts';
import type { Prompt, PromptFilters, PromptPriority, PromptStatus } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Prompts', href: index() }],
    },
});

const props = defineProps<{
    prompts: Prompt[];
    filters: PromptFilters;
    canReorder: boolean;
}>();

const page = usePage();
const editing = ref<Prompt | null>(null);

const { filters, setFilter, search } = usePromptFilters(() => props.filters);

const captureProjectId = computed<number | null>(() => {
    const project = filters.value.project;

    if (project === null || project === 'inbox') {
        return null;
    }

    return Number(project);
});

const heading = computed<string>(() => {
    const project = filters.value.project;

    if (project === null) {
        return 'All prompts';
    }

    if (project === 'inbox') {
        return 'Inbox';
    }

    return (
        page.props.projects.find((candidate) => String(candidate.id) === project)
            ?.name ?? 'Prompts'
    );
});

const toggle = <T extends string>(list: T[], value: T): T[] =>
    list.includes(value)
        ? list.filter((entry) => entry !== value)
        : [...list, value];
</script>

<template>
    <Head title="Prompts" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
        <h1 class="text-lg font-semibold">{{ heading }}</h1>

        <QuickCapture :project-id="captureProjectId" />

        <FilterBar
            :filters="filters"
            @search="search"
            @toggle-status="setFilter('status', toggle<PromptStatus>(filters.status, $event))"
            @toggle-priority="setFilter('priority', toggle<PromptPriority>(filters.priority, $event))"
            @toggle-tag="setFilter('tags', toggle<string>(filters.tags, $event))"
        />

        <PromptList
            :prompts="props.prompts"
            :can-reorder="props.canReorder"
            :project-id="captureProjectId"
            @edit="editing = $event"
        />

        <PromptEditSheet :prompt="editing" @close="editing = null" />
    </div>
</template>
