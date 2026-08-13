<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { GripVertical } from '@lucide/vue';
import { computed } from 'vue';
import PromptPriorityController from '@/actions/App/Http/Controllers/PromptPriorityController';
import PromptStatusController from '@/actions/App/Http/Controllers/PromptStatusController';
import PromptPriorityPill from '@/components/prompts/PromptPriorityPill.vue';
import PromptStatusPill from '@/components/prompts/PromptStatusPill.vue';
import { PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import { index } from '@/routes/prompts';
import type { Prompt, PromptPriority, PromptStatus } from '@/types';

const { prompt, selected, draggable } = defineProps<{
    prompt: Prompt;
    selected: boolean;
    draggable: boolean;
}>();

const emit = defineEmits<{ select: [] }>();

const page = usePage();

const project = computed(
    () =>
        page.props.projects.find(
            (candidate) => candidate.id === prompt.projectId,
        ) ?? null,
);

const projectHref = computed(() =>
    index({
        query: {
            project:
                prompt.projectId === null ? 'inbox' : String(prompt.projectId),
        },
    }),
);

const preview = computed(
    () => prompt.body.split('\n')[0] || 'Empty — open to write it',
);

const setStatus = (status: PromptStatus): void => {
    router.patch(
        PromptStatusController.url({ prompt: prompt.id }),
        { status },
        { preserveScroll: true, preserveState: true, only: ['prompts'] },
    );
};

const setPriority = (priority: PromptPriority): void => {
    router.patch(
        PromptPriorityController.url({ prompt: prompt.id }),
        { priority },
        { preserveScroll: true, preserveState: true, only: ['prompts'] },
    );
};
</script>

<template>
    <div
        class="relative flex cursor-pointer flex-col gap-2.5 rounded-[13px] border px-3.5 pt-3.5 pb-2.5"
        :class="[
            selected
                ? 'border-border-selected bg-surface-selected'
                : 'border-border bg-card',
            prompt.status === 'done' ? 'opacity-55' : 'opacity-100',
        ]"
        @click="emit('select')"
    >
        <div class="flex items-center gap-2.5">
            <GripVertical
                v-if="draggable"
                class="prompt-drag-handle size-3.5 shrink-0 cursor-grab text-ghost-foreground"
                @click.stop
            />

            <PromptStatusPill
                :model-value="prompt.status"
                size="sm"
                @update:model-value="setStatus"
            />

            <Link
                :href="projectHref"
                class="flex items-center gap-1.5 text-[11.5px] text-muted-foreground hover:text-foreground"
                @click.stop
            >
                <span
                    class="size-1.5 rounded-full"
                    :class="
                        project
                            ? PROJECT_DOT_CLASSES[project.color]
                            : 'bg-faint-foreground'
                    "
                />
                {{ prompt.projectName ?? 'No project' }}
            </Link>

            <div class="flex-1" />

            <PromptPriorityPill
                :model-value="prompt.priority"
                size="sm"
                align="end"
                @update:model-value="setPriority"
            />
        </div>

        <div
            class="text-[15px] font-semibold tracking-tight"
            :class="
                prompt.status === 'done'
                    ? 'text-secondary-foreground'
                    : 'text-foreground'
            "
        >
            {{ prompt.title || 'Untitled prompt' }}
        </div>
        <div
            class="max-h-[38px] overflow-hidden font-mono text-xs leading-relaxed text-subtle-foreground"
        >
            {{ preview }}
        </div>
    </div>
</template>
