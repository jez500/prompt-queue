<script setup lang="ts">
import PromptRow from '@/components/prompts/PromptRow.vue';
import type { Prompt } from '@/types';

const { prompts, canReorder } = defineProps<{
    prompts: Prompt[];
    canReorder: boolean;
}>();

const emit = defineEmits<{ edit: [prompt: Prompt] }>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <p
            v-if="prompts.length === 0"
            class="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Nothing here yet. Type a prompt above to capture one.
        </p>

        <PromptRow
            v-for="prompt in prompts"
            :key="prompt.id"
            :prompt="prompt"
            :draggable="canReorder"
            @edit="emit('edit', $event)"
        />
    </div>
</template>
