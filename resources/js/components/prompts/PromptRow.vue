<script setup lang="ts">
import { Copy, GripVertical, Pencil } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCopyPrompt } from '@/composables/useCopyPrompt';
import type { Prompt } from '@/types';

const { prompt, draggable } = defineProps<{
    prompt: Prompt;
    draggable: boolean;
}>();

const emit = defineEmits<{ edit: [prompt: Prompt] }>();

const { copy } = useCopyPrompt();
</script>

<template>
    <div
        class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
    >
        <GripVertical
            v-if="draggable"
            class="prompt-drag-handle mt-1 size-4 shrink-0 cursor-grab text-muted-foreground"
        />

        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ prompt.title }}</p>
            <p
                :data-prompt-body="prompt.id"
                class="mt-1 line-clamp-2 font-mono text-xs text-muted-foreground"
            >
                {{ prompt.body }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-1">
                <Badge variant="outline" class="capitalize">{{ prompt.status }}</Badge>
                <Badge
                    v-if="prompt.priority !== 'normal'"
                    variant="secondary"
                    class="capitalize"
                >
                    {{ prompt.priority }}
                </Badge>
                <Badge v-for="tag in prompt.tags" :key="tag" variant="outline">
                    #{{ tag }}
                </Badge>
            </div>
        </div>

        <div class="flex shrink-0 gap-1">
            <Button
                size="icon"
                variant="ghost"
                aria-label="Copy prompt"
                @click="copy(prompt)"
            >
                <Copy class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                aria-label="Edit prompt"
                @click="emit('edit', prompt)"
            >
                <Pencil class="size-4" />
            </Button>
        </div>
    </div>
</template>
