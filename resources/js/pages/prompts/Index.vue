<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, inject, ref, watch } from 'vue';
import type { Ref } from 'vue';
import ProjectEditSheet from '@/components/projects/ProjectEditSheet.vue';
import PromptDetailPane from '@/components/prompts/PromptDetailPane.vue';
import PromptListPane from '@/components/prompts/PromptListPane.vue';
import { usePromptFilters } from '@/composables/usePromptFilters';
import { useShellBreakpoints } from '@/composables/useShellBreakpoints';
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
    /* The one prompt carrying a body — resolved server-side from ?prompt=. */
    selected: Prompt | null;
    filters: PromptFilters;
    canReorder: boolean;
}>();

const page = usePage();
const { narrow } = useShellBreakpoints();

/* A draft is the one selection the server cannot know about yet. */
const drafting = ref(false);
const openDetail = ref(false);
const editingProject = ref<Project | null>(null);

const { filters, setFilter, search, selectPrompt } = usePromptFilters(
    () => props.filters,
);

const selectedId = computed<number | 'new' | null>(() =>
    drafting.value ? 'new' : (props.selected?.id ?? null),
);

/* Leaving the draft once it has been saved, or the list has moved on. */
watch(
    () => props.selected?.id,
    () => {
        if (!drafting.value) {
            openDetail.value = openDetail.value && props.selected !== null;
        }
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
        : 'bg-faint-foreground',
);

const createDraft = (): void => {
    drafting.value = true;
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
    drafting.value = false;
    openDetail.value = true;

    if (prompt.id !== props.selected?.id) {
        selectPrompt(prompt.id);
    }
};

const handleCreated = (id: number): void => {
    drafting.value = false;
    selectPrompt(id);
};

const handleDeleted = (): void => {
    drafting.value = false;
    openDetail.value = false;
    selectPrompt(null);
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
        :prompt="props.selected"
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
