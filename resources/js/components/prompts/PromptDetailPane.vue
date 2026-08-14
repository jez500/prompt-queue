<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, RotateCcw, Trash2 } from '@lucide/vue';
import type { Ref } from 'vue';
import { computed, inject, nextTick, ref, watch } from 'vue';
import PromptPriorityPill from '@/components/prompts/PromptPriorityPill.vue';
import PromptProjectPill from '@/components/prompts/PromptProjectPill.vue';
import PromptStatusPill from '@/components/prompts/PromptStatusPill.vue';
import AppPane from '@/components/shell/AppPane.vue';
import PaneHeader from '@/components/shell/PaneHeader.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCopyPrompt } from '@/composables/useCopyPrompt';
import { shortcutHint } from '@/composables/useKeyboardShortcuts';
import { usePromptAutosave } from '@/composables/usePromptAutosave';
import { useShellBreakpoints } from '@/composables/useShellBreakpoints';
import { PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import { formatRelativeTime } from '@/lib/relativeTime';
import type { Prompt } from '@/types';

const { prompt, isNew, narrow, draftProjectId } = defineProps<{
    prompt: Prompt | null;
    isNew: boolean;
    narrow: boolean;
    draftProjectId: number | null;
}>();

const emit = defineEmits<{
    back: [];
    created: [id: number];
    moved: [projectId: number | null, promptId: number];
    deleted: [];
}>();

const page = usePage();
const { copy } = useCopyPrompt();
const { compact } = useShellBreakpoints();

const autosave = usePromptAutosave({
    prompt: () => prompt,
    isNew: () => isNew,
    projectId: () => draftProjectId,
    onCreated: (id) => emit('created', id),
});

const titleInput = ref<HTMLInputElement | null>(null);
const tagDraft = ref('');
const copiedId = ref<number | null>(null);
let copiedTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => isNew,
    (value) => {
        if (value) {
            nextTick(() => titleInput.value?.focus());
        }
    },
    { immediate: true },
);

const project = computed(() => {
    const id = isNew ? draftProjectId : (prompt?.projectId ?? null);

    if (id === null) {
        return null;
    }

    return page.props.projects.find((candidate) => candidate.id === id) ?? null;
});

const saveLabel = computed(() => {
    if (autosave.saving.value) {
        return 'Saving…';
    }

    if (autosave.failed.value) {
        return 'Not saved';
    }

    if (autosave.savedAt.value) {
        return 'Saved';
    }

    return 'Auto-saves';
});

const doneToggleLabel = computed(() =>
    prompt?.status === 'done' ? 'Reopen' : 'Mark done',
);

/* The header must stay on one line in the compact band, so the label sheds. */
const copyLabel = computed(() => {
    if (copiedId.value === prompt?.id) {
        return 'Copied';
    }

    return compact.value ? 'Copy' : 'Copy prompt';
});

const saveDotClass = computed(() => {
    if (autosave.saving.value) {
        return 'bg-muted-foreground';
    }

    if (autosave.failed.value) {
        return 'bg-[#C43350] dark:bg-[#FF8B9C]';
    }

    return autosave.savedAt.value
        ? 'bg-[#1F7A55] dark:bg-[#6FCFA1]'
        : 'bg-ghost-foreground';
});

const saveTextClass = computed(() => {
    if (autosave.saving.value) {
        return 'text-muted-foreground';
    }

    if (autosave.failed.value) {
        return 'text-[#C43350] dark:text-[#FF8B9C]';
    }

    return autosave.savedAt.value
        ? 'text-[#1F7A55] dark:text-[#6FCFA1]'
        : 'text-ghost-foreground';
});

const toggleDone = (): void => {
    if (!prompt) {
        return;
    }

    autosave.setStatus(prompt.status === 'done' ? 'todo' : 'done');
};

const handleCopy = async (): Promise<void> => {
    if (!prompt) {
        return;
    }

    await copy(prompt, autosave.body.value);
    copiedId.value = prompt.id;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => {
        copiedId.value = null;
    }, 1400);
};

/* The copy shortcut, signalled from the shell. */
const copySignal = inject<Ref<number>>('promptQueueCopy');

watch(
    () => copySignal?.value,
    (value, oldValue) => {
        if (value !== undefined && oldValue !== undefined && !isNew) {
            void handleCopy();
        }
    },
);

const handleDelete = (): void => {
    if (!window.confirm('Delete this prompt? This cannot be undone.')) {
        return;
    }

    autosave.destroy();
    emit('deleted');
};

const addTag = (): void => {
    const name = tagDraft.value.trim();
    tagDraft.value = '';

    if (name === '' || autosave.tags.value.includes(name)) {
        return;
    }

    autosave.updateTags([...autosave.tags.value, name]);
};

/**
 * Move the prompt, and tell the page which prompt went where. The id is
 * captured here because by the time the move lands the page's `selected` has
 * already moved on to whatever is left in the bucket being viewed.
 */
const handleProjectChange = (projectId: number | null): void => {
    const movedId = prompt?.id;

    if (movedId === undefined) {
        return;
    }

    autosave.setProject(projectId, () => emit('moved', projectId, movedId));
};

const removeTag = (name: string): void => {
    autosave.updateTags(autosave.tags.value.filter((tag) => tag !== name));
};
</script>

<template>
    <AppPane variant="detail" :narrow="narrow">
        <template v-if="isNew || prompt">
            <PaneHeader :narrow="narrow" @back="emit('back')">
                <div class="flex items-center gap-1.5">
                    <span class="size-1.5 rounded-full" :class="saveDotClass" />
                    <span
                        class="font-mono text-[10.5px] tracking-[0.04em]"
                        :class="saveTextClass"
                    >
                        {{ saveLabel }}
                    </span>
                </div>

                <template #actions>
                    <Tooltip v-if="!isNew">
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                aria-label="Delete prompt"
                                class="flex size-8 flex-none items-center justify-center rounded-[9px] border border-border-strong text-muted-foreground hover:border-[#E8B4BF] hover:text-[#C43350] dark:hover:border-[#5A2733] dark:hover:text-[#FF8B9C]"
                                @click="handleDelete"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>Delete prompt</TooltipContent>
                    </Tooltip>

                    <Tooltip v-if="!isNew && prompt">
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                :aria-label="doneToggleLabel"
                                class="flex size-8 flex-none items-center justify-center rounded-[9px] border border-border-strong text-secondary-foreground"
                                :class="
                                    prompt.status === 'done'
                                        ? 'hover:border-border-hover hover:text-foreground'
                                        : 'hover:border-[#A7D8C0] hover:text-[#1F7A55] dark:hover:border-[#2E7D5B] dark:hover:text-[#6FCFA1]'
                                "
                                @click="toggleDone"
                            >
                                <RotateCcw
                                    v-if="prompt.status === 'done'"
                                    class="size-4"
                                />
                                <Check v-else class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>{{ doneToggleLabel }}</TooltipContent>
                    </Tooltip>

                    <button
                        type="button"
                        :disabled="isNew || !prompt"
                        class="flex h-8 flex-none items-center gap-2.5 rounded-[9px] bg-primary text-[13px] font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-40"
                        :class="compact ? 'px-3' : 'px-4.5'"
                        @click="handleCopy"
                    >
                        <span>{{ copyLabel }}</span>
                        <span class="font-mono text-[11px] opacity-75">{{
                            shortcutHint('copy')
                        }}</span>
                    </button>
                </template>
            </PaneHeader>

            <div
                class="relative flex flex-1 flex-col gap-[18px] overflow-hidden p-4"
            >
                <div class="flex flex-wrap items-center gap-2.5">
                    <PromptStatusPill
                        :model-value="autosave.status.value"
                        @update:model-value="autosave.setStatus"
                    />

                    <PromptPriorityPill
                        :model-value="autosave.priority.value"
                        @update:model-value="autosave.setPriority"
                    />

                    <PromptProjectPill
                        v-if="!isNew && prompt"
                        :model-value="prompt.projectId"
                        @update:model-value="handleProjectChange"
                    />
                    <!-- A draft is filed by the scope it was captured in;
                         there is no prompt to move yet. -->
                    <div
                        v-else
                        class="flex items-center gap-1.5 text-[12.5px] text-muted-foreground"
                    >
                        <span
                            class="size-1.5 rounded-full"
                            :class="
                                project
                                    ? PROJECT_DOT_CLASSES[project.color]
                                    : 'bg-faint-foreground'
                            "
                        />
                        {{ project?.name ?? 'No project' }}
                    </div>
                </div>

                <input
                    ref="titleInput"
                    v-model="autosave.title.value"
                    placeholder="Untitled prompt"
                    class="w-full bg-transparent font-sans font-bold tracking-tight text-foreground outline-none"
                    :class="narrow ? 'text-[22px]' : 'text-[29px]'"
                />
                <textarea
                    v-model="autosave.body.value"
                    :data-prompt-body="prompt?.id"
                    placeholder="Write the prompt you want to hand an agent…"
                    class="w-full flex-1 resize-none rounded-[14px] border border-border bg-card p-6 font-mono text-sm leading-[1.75] text-editor-foreground outline-none focus:border-ring"
                />

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="tag in autosave.tags.value"
                        :key="tag"
                        class="flex h-[26px] items-center gap-1.5 rounded-full bg-muted px-2.5 font-mono text-[11px] text-muted-foreground"
                    >
                        {{ tag }}
                        <button
                            type="button"
                            :aria-label="`Remove ${tag}`"
                            class="text-faint-foreground hover:text-foreground"
                            @click="removeTag(tag)"
                        >
                            ×
                        </button>
                    </span>
                    <input
                        v-model="tagDraft"
                        placeholder="Add a tag…"
                        class="h-[26px] w-28 rounded-full bg-muted px-2.5 font-mono text-[11px] text-foreground outline-none placeholder:text-ghost-foreground"
                        @keydown.enter.prevent="addTag"
                    />
                    <div class="flex-1" />
                    <!-- A draft has never been saved, so it has no time to
                         show and nothing to have copied. -->
                    <div
                        v-if="prompt"
                        class="font-mono text-[11px] text-ghost-foreground"
                    >
                        {{ formatRelativeTime(prompt.updatedAt)
                        }}{{ copiedId === prompt.id ? ' · copied' : '' }}
                    </div>
                </div>
            </div>
        </template>

        <div
            v-else
            class="flex flex-1 flex-col items-center justify-center gap-2 text-center text-faint-foreground"
        >
            <p class="text-[14.5px] font-semibold text-muted-foreground">
                No prompt selected
            </p>
            <p class="max-w-[240px] text-[12.5px] leading-relaxed">
                Pick one from the list, or create a new prompt to get started.
            </p>
        </div>
    </AppPane>
</template>
