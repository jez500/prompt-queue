<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import PromptController from '@/actions/App/Http/Controllers/PromptController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

const { projectId } = defineProps<{ projectId: number | null }>();

const textarea = ref<HTMLTextAreaElement | null>(null);

const form = useForm<{ body: string; project: number | null }>({
    body: '',
    project: null,
});

onMounted(() => {
    textarea.value?.focus();
});

const submit = (): void => {
    if (form.body.trim() === '') {
        return;
    }

    form.project = projectId;

    form.post(PromptController.store.url(), {
        preserveScroll: true,
        only: ['prompts', 'projects', 'canReorder'],
        onSuccess: () => {
            form.reset('body');
            textarea.value?.focus();
        },
    });
};
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
        <textarea
            ref="textarea"
            v-model="form.body"
            rows="3"
            placeholder="Type a prompt… ⌘/Ctrl + Enter to save"
            class="w-full resize-y bg-transparent font-mono text-sm outline-none placeholder:text-muted-foreground"
            @keydown.enter.meta.prevent="submit"
            @keydown.enter.ctrl.prevent="submit"
        />
        <InputError class="mt-2" :message="form.errors.body" />
        <div class="mt-2 flex justify-end">
            <Button size="sm" :disabled="form.processing" @click="submit">
                Capture
            </Button>
        </div>
    </div>
</template>
