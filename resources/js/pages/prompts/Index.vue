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

const { filters, setFilter, search, selectPrompt, followPrompt } =
    usePromptFilters(() => props.filters);

/*
  A prompt this page has just created. The server only sends a body for the
  prompt named in ?prompt=, so there is a round trip between the create
  landing and the new prompt arriving as `selected` — and the editor must not
  be handed the previous selection in the meantime.
*/
const pendingCreatedId = ref<number | null>(null);

const selectedId = computed<number | 'new' | null>(() =>
    drafting.value ? 'new' : (props.selected?.id ?? null),
);

/*
  What the editor edits. A draft has no server-side prompt, and neither does
  a create still in flight; either way the editor keeps what was typed rather
  than adopting whatever was selected before.
*/
const editedPrompt = computed<Prompt | null>(() => {
    if (drafting.value) {
        return null;
    }

    if (
        pendingCreatedId.value !== null &&
        props.selected?.id !== pendingCreatedId.value
    ) {
        return null;
    }

    return props.selected;
});

/* Leaving the draft once it has been saved, or the list has moved on. */
watch(
    () => props.selected?.id,
    (id) => {
        if (id !== undefined && id === pendingCreatedId.value) {
            pendingCreatedId.value = null;
        }

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

    /* Picking anything other than the prompt just created abandons the wait
       for it, or the editor would sit empty until an id that is no longer
       coming arrives. */
    if (prompt.id !== pendingCreatedId.value) {
        pendingCreatedId.value = null;
    }

    if (prompt.id !== props.selected?.id) {
        selectPrompt(prompt.id);
    }
};

const handleCreated = (id: number): void => {
    /* Cleared before the draft, so the editor never sees the old selection
       in the gap between the two. */
    pendingCreatedId.value = id;
    drafting.value = false;
    selectPrompt(id);
};

/*
  A prompt moved out of the project being viewed would otherwise vanish from
  the list and take the editor with it, so the list follows it across. From
  "All prompts" there is nothing to follow: it is still listed, still open.
*/
const handleMoved = (projectId: number | null, promptId: number): void => {
    if (filters.value.project === null) {
        return;
    }

    followPrompt(projectId, promptId);
};

const handleDeleted = (): void => {
    pendingCreatedId.value = null;
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
        :prompt="editedPrompt"
        :is-new="selectedId === 'new'"
        :narrow="narrow"
        :draft-project-id="captureProjectId"
        @back="openDetail = false"
        @created="handleCreated"
        @moved="handleMoved"
        @deleted="handleDeleted"
    />

    <ProjectEditSheet
        :project="editingProject"
        @close="editingProject = null"
    />
</template>
