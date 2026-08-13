<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { GripVertical } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import PromptPriorityController from '@/actions/App/Http/Controllers/PromptPriorityController';
import PromptStatusController from '@/actions/App/Http/Controllers/PromptStatusController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import {
    PROMPT_PRIORITY_LABELS,
    PROMPT_PRIORITY_QUEUE_PILL_CLASSES,
} from '@/lib/promptPriority';
import {
    PROMPT_STATUS_LABELS,
    PROMPT_STATUS_QUEUE_DOT_CLASSES,
    PROMPT_STATUS_QUEUE_PILL_CLASSES,
} from '@/lib/promptStatus';
import { index } from '@/routes/prompts';
import type { Prompt, PromptPriority, PromptStatus } from '@/types';

const { prompt, selected, draggable } = defineProps<{
    prompt: Prompt;
    selected: boolean;
    draggable: boolean;
}>();

const emit = defineEmits<{ select: [] }>();

const page = usePage();
const statuses: PromptStatus[] = ['todo', 'implementing', 'done'];
const priorities: PromptPriority[] = ['high', 'normal', 'low'];

const project = () =>
    page.props.projects.find(
        (candidate) => candidate.id === prompt.projectId,
    ) ?? null;

const setStatus = (status: AcceptableValue): void => {
    if (typeof status !== 'string' || status === prompt.status) {
        return;
    }

    router.patch(
        PromptStatusController.url({ prompt: prompt.id }),
        { status },
        { preserveScroll: true, preserveState: true, only: ['prompts'] },
    );
};

const setPriority = (priority: AcceptableValue): void => {
    if (typeof priority !== 'string' || priority === prompt.priority) {
        return;
    }

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
                ? 'border-[#35354A] bg-[#13131A]'
                : 'border-[#1C1C24] bg-[#0D0D12]',
            prompt.status === 'done' ? 'opacity-55' : 'opacity-100',
        ]"
        @click="emit('select')"
    >
        <div class="flex items-center gap-2.5">
            <GripVertical
                v-if="draggable"
                class="prompt-drag-handle size-3.5 shrink-0 cursor-grab text-[#4A4A55]"
                @click.stop
            />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex h-5 items-center gap-1.5 rounded-md px-2 font-mono text-[10px] tracking-[0.06em] uppercase"
                        :class="PROMPT_STATUS_QUEUE_PILL_CLASSES[prompt.status]"
                        @click.stop
                    >
                        <span>{{ PROMPT_STATUS_LABELS[prompt.status] }}</span>
                        <span class="text-[8px] opacity-70">▾</span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    class="w-40 border-[#2E2E3A] bg-[#15151C] text-[#F2F2F4]"
                >
                    <DropdownMenuRadioGroup
                        :model-value="prompt.status"
                        @update:model-value="setStatus"
                    >
                        <DropdownMenuRadioItem
                            v-for="status in statuses"
                            :key="status"
                            :value="status"
                            class="gap-2 focus:bg-[#22222C] focus:text-[#F2F2F4]"
                        >
                            <span
                                class="size-1.5 rounded-full"
                                :class="PROMPT_STATUS_QUEUE_DOT_CLASSES[status]"
                            />
                            {{ PROMPT_STATUS_LABELS[status] }}
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            <Link
                :href="
                    prompt.projectId === null
                        ? index({ query: { project: 'inbox' } })
                        : index({
                              query: { project: String(prompt.projectId) },
                          })
                "
                class="flex items-center gap-1.5 text-[11.5px] text-[#8A8A96] hover:text-[#F2F2F4]"
                @click.stop
            >
                <span
                    class="size-1.5 rounded-full"
                    :class="
                        project()
                            ? PROJECT_DOT_CLASSES[project()!.color]
                            : 'bg-[#5A5A66]'
                    "
                />
                {{ prompt.projectName ?? 'No project' }}
            </Link>

            <div class="flex-1" />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex h-5 items-center gap-1.5 rounded-md px-2 font-mono text-[10px] tracking-[0.06em] uppercase"
                        :class="
                            PROMPT_PRIORITY_QUEUE_PILL_CLASSES[prompt.priority]
                        "
                        @click.stop
                    >
                        <span>{{
                            PROMPT_PRIORITY_LABELS[prompt.priority]
                        }}</span>
                        <span class="text-[8px] opacity-70">▾</span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    class="w-36 border-[#2E2E3A] bg-[#15151C] text-[#F2F2F4]"
                >
                    <DropdownMenuRadioGroup
                        :model-value="prompt.priority"
                        @update:model-value="setPriority"
                    >
                        <DropdownMenuRadioItem
                            v-for="priority in priorities"
                            :key="priority"
                            :value="priority"
                            class="focus:bg-[#22222C] focus:text-[#F2F2F4]"
                        >
                            {{ PROMPT_PRIORITY_LABELS[priority] }}
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div
            class="text-[15px] font-semibold tracking-tight"
            :class="
                prompt.status === 'done' ? 'text-[#9A9AA6]' : 'text-[#F2F2F4]'
            "
        >
            {{ prompt.title || 'Untitled prompt' }}
        </div>
        <div
            class="max-h-[38px] overflow-hidden font-mono text-xs leading-relaxed text-[#63636F]"
        >
            {{ prompt.body.split('\n')[0] || 'Empty — open to write it' }}
        </div>
    </div>
</template>
