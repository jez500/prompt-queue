<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import FilterBar from '@/components/prompts/FilterBar.vue';
import PromptQueueCard from '@/components/prompts/PromptQueueCard.vue';
import AppPane from '@/components/shell/AppPane.vue';
import NarrowTopBar from '@/components/shell/NarrowTopBar.vue';
import { reorder } from '@/routes/prompts';
import type {
    Project,
    Prompt,
    PromptFilters,
    PromptPriority,
    PromptStatus,
} from '@/types';

const { prompts, filters, canReorder, projectId, selectedId, narrow } =
    defineProps<{
        prompts: Prompt[];
        filters: PromptFilters;
        canReorder: boolean;
        projectId: number | null;
        selectedId: number | 'new' | null;
        narrow: boolean;
        heading: string;
        scopeDotClass: string;
        scopeCount: number;
        selectedProject: Project | null;
    }>();

const emit = defineEmits<{
    select: [prompt: Prompt];
    editProject: [];
    newPrompt: [];
    search: [term: string];
    toggleStatus: [status: PromptStatus];
    togglePriority: [priority: PromptPriority];
    toggleTag: [tag: string];
}>();

const ordered = ref<Prompt[]>([...prompts]);

watch(
    () => prompts,
    (value) => {
        ordered.value = [...value];
    },
);

const persist = (): void => {
    const snapshot = [...prompts];

    router.patch(
        reorder.url(),
        { project: projectId, ids: ordered.value.map((prompt) => prompt.id) },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['prompts'],
            onError: () => {
                ordered.value = snapshot;
            },
        },
    );
};
</script>

<template>
    <AppPane variant="list" :narrow="narrow">
        <NarrowTopBar v-if="narrow" @new-prompt="emit('newPrompt')" />

        <div class="flex flex-col gap-3 px-[18px] pt-[18px] pb-3">
            <div class="flex items-center gap-2.5">
                <span class="size-1.5 rounded-full" :class="scopeDotClass" />
                <h1 class="text-[19px] font-bold tracking-tight">
                    {{ heading }}
                </h1>
                <span
                    class="pt-0.5 font-mono text-[11px] text-subtle-foreground"
                >
                    {{ scopeCount }}
                </span>
                <button
                    v-if="selectedProject"
                    type="button"
                    aria-label="Edit project"
                    class="text-subtle-foreground hover:text-foreground"
                    @click="emit('editProject')"
                >
                    <Pencil class="size-3.5" />
                </button>
            </div>

            <FilterBar
                :filters="filters"
                @search="emit('search', $event)"
                @toggle-status="emit('toggleStatus', $event)"
                @toggle-priority="emit('togglePriority', $event)"
                @toggle-tag="emit('toggleTag', $event)"
            />
        </div>

        <div class="flex flex-1 flex-col gap-2 overflow-y-auto px-3 pb-3.5">
            <div
                v-if="prompts.length === 0"
                class="mt-10 flex flex-col items-center gap-2.5 px-7 text-center"
            >
                <div
                    class="flex size-8 items-center justify-center rounded-[10px] border border-dashed border-ring text-[15px] text-ghost-foreground"
                >
                    +
                </div>
                <div class="text-[14.5px] font-semibold">Nothing here yet</div>
                <div
                    class="text-[12.5px] leading-relaxed text-subtle-foreground"
                >
                    Capture the next thing you want an agent to pick up.
                </div>
            </div>

            <draggable
                v-else-if="canReorder"
                v-model="ordered"
                item-key="id"
                handle=".prompt-drag-handle"
                class="flex flex-col gap-2"
                @end="persist"
            >
                <template #item="{ element }: { element: Prompt }">
                    <PromptQueueCard
                        :prompt="element"
                        :selected="element.id === selectedId"
                        :draggable="true"
                        @select="emit('select', element)"
                    />
                </template>
            </draggable>

            <template v-else>
                <PromptQueueCard
                    v-for="prompt in ordered"
                    :key="prompt.id"
                    :prompt="prompt"
                    :selected="prompt.id === selectedId"
                    :draggable="false"
                    @select="emit('select', prompt)"
                />
            </template>
        </div>
    </AppPane>
</template>
