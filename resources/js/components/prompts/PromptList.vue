<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import PromptRow from '@/components/prompts/PromptRow.vue';
import { reorder } from '@/routes/prompts';
import type { Prompt } from '@/types';

const { prompts, canReorder, projectId } = defineProps<{
    prompts: Prompt[];
    canReorder: boolean;
    projectId: number | null;
}>();

const emit = defineEmits<{ edit: [prompt: Prompt] }>();

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
        {
            project: projectId,
            ids: ordered.value.map((prompt) => prompt.id),
        },
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
    <div class="flex flex-col gap-2">
        <p
            v-if="ordered.length === 0"
            class="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Nothing here yet. Type a prompt above to capture one.
        </p>

        <draggable
            v-else-if="canReorder"
            v-model="ordered"
            item-key="id"
            handle=".prompt-drag-handle"
            class="flex flex-col gap-2"
            @end="persist"
        >
            <template #item="{ element }: { element: Prompt }">
                <PromptRow
                    :prompt="element"
                    :draggable="true"
                    @edit="emit('edit', $event)"
                />
            </template>
        </draggable>

        <template v-else>
            <PromptRow
                v-for="prompt in ordered"
                :key="prompt.id"
                :prompt="prompt"
                :draggable="false"
                @edit="emit('edit', $event)"
            />
        </template>
    </div>
</template>
