<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, inject, ref, watch } from 'vue';
import type { Ref } from 'vue';
import ProjectEditSheet from '@/components/projects/ProjectEditSheet.vue';
import PromptDetailPane from '@/components/prompts/PromptDetailPane.vue';
import PromptListPane from '@/components/prompts/PromptListPane.vue';
import { usePromptFilters } from '@/composables/usePromptFilters';
import { PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import type {
    Project,
    Prompt,
    PromptFilters,
    PromptPriority,
    PromptStatus,
} from '@/types';

const props = defineProps<{
    prompts: Prompt[];
    filters: PromptFilters;
    canReorder: boolean;
}>();

const page = usePage();
const narrow = useMediaQuery('(max-width: 1099px)');

const selectedId = ref<number | 'new' | null>(props.prompts[0]?.id ?? null);
const openDetail = ref(false);
const editingProject = ref<Project | null>(null);

const { filters, setFilter, search } = usePromptFilters(() => props.filters);

watch(
    () => props.prompts,
    (list) => {
        if (selectedId.value === 'new') {
            return;
        }

        if (
            selectedId.value !== null &&
            list.some((prompt) => prompt.id === selectedId.value)
        ) {
            return;
        }

        selectedId.value = list[0]?.id ?? null;
    },
);

const captureProjectId = computed<number | null>(() => {
    const project = filters.value.project;

    if (project === null || project === 'inbox') {
        return null;
    }

    return Number(project);
});

const selectedProject = computed<Project | null>(() => {
    const project = filters.value.project;

    if (project === null || project === 'inbox') {
        return null;
    }

    return (
        page.props.projects.find(
            (candidate) => String(candidate.id) === project,
        ) ?? null
    );
});

const heading = computed<string>(() => {
    const project = filters.value.project;

    if (project === null) {
        return 'All prompts';
    }

    if (project === 'inbox') {
        return 'No project';
    }

    return selectedProject.value?.name ?? 'Prompts';
});

const scopeDotClass = computed<string>(() =>
    selectedProject.value
        ? PROJECT_DOT_CLASSES[selectedProject.value.color]
        : 'bg-[#5A5A66]',
);

const selected = computed<Prompt | null>(() => {
    if (selectedId.value === 'new') {
        return null;
    }

    return (
        props.prompts.find((prompt) => prompt.id === selectedId.value) ??
        props.prompts[0] ??
        null
    );
});

const createDraft = (): void => {
    selectedId.value = 'new';
    openDetail.value = true;
};

const newPromptSignal = inject<Ref<number>>('promptQueueNewPrompt');

watch(
    () => newPromptSignal?.value,
    (value, oldValue) => {
        if (value !== undefined && oldValue !== undefined) {
            createDraft();
        }
    },
);

const handleSelect = (prompt: Prompt): void => {
    selectedId.value = prompt.id;
    openDetail.value = true;
};

const handleCreated = (id: number): void => {
    selectedId.value = id;
};

const handleDeleted = (): void => {
    selectedId.value = null;
    openDetail.value = false;
};

const toggle = <T extends string>(list: T[], value: T): T[] =>
    list.includes(value)
        ? list.filter((entry) => entry !== value)
        : [...list, value];
</script>

<template>
    <Head title="Prompts" />

    <PromptListPane
        v-if="!narrow || !openDetail"
        :prompts="props.prompts"
        :filters="filters"
        :can-reorder="props.canReorder"
        :project-id="captureProjectId"
        :selected-id="selectedId"
        :narrow="narrow"
        :heading="heading"
        :scope-dot-class="scopeDotClass"
        :scope-count="props.prompts.length"
        :selected-project="selectedProject"
        @select="handleSelect"
        @edit-project="editingProject = selectedProject"
        @new-prompt="createDraft"
        @search="search"
        @toggle-status="
            setFilter('status', toggle<PromptStatus>(filters.status, $event))
        "
        @toggle-priority="
            setFilter(
                'priority',
                toggle<PromptPriority>(filters.priority, $event),
            )
        "
        @toggle-tag="setFilter('tags', toggle<string>(filters.tags, $event))"
    />

    <PromptDetailPane
        v-if="!narrow || openDetail"
        :prompt="selected"
        :is-new="selectedId === 'new'"
        :narrow="narrow"
        :draft-project-id="captureProjectId"
        @back="openDetail = false"
        @new="createDraft"
        @created="handleCreated"
        @deleted="handleDeleted"
    />

    <ProjectEditSheet
        :project="editingProject"
        @close="editingProject = null"
    />
</template>
