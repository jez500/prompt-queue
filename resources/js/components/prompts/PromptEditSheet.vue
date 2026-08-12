<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PromptController from '@/actions/App/Http/Controllers/PromptController';
import InputError from '@/components/InputError.vue';
import TagInput from '@/components/prompts/TagInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { Prompt, PromptPriority, PromptStatus } from '@/types';

const { prompt } = defineProps<{ prompt: Prompt | null }>();

const emit = defineEmits<{ close: [] }>();

const page = usePage();
const projects = computed(() => page.props.projects);

const form = useForm<{
    title: string;
    body: string;
    status: PromptStatus;
    priority: PromptPriority;
    project: string;
    tags: string[];
}>({
    title: '',
    body: '',
    status: 'todo',
    priority: 'normal',
    project: 'inbox',
    tags: [],
});

watch(
    () => prompt,
    (value) => {
        if (!value) {
            return;
        }

        form.defaults({
            title: value.rawTitle ?? '',
            body: value.body,
            status: value.status,
            priority: value.priority,
            project: value.projectId === null ? 'inbox' : String(value.projectId),
            tags: [...value.tags],
        });
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

const save = (): void => {
    if (!prompt) {
        return;
    }

    form
        .transform((data) => ({
            ...data,
            title: data.title.trim() === '' ? null : data.title,
            project: data.project === 'inbox' ? null : Number(data.project),
        }))
        .patch(PromptController.update.url({ prompt: prompt.id }), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
};

const destroy = (): void => {
    if (!prompt) {
        return;
    }

    form.delete(PromptController.destroy.url({ prompt: prompt.id }), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Sheet
        :open="prompt !== null"
        @update:open="(open: boolean) => !open && emit('close')"
    >
        <SheetContent class="flex w-full flex-col gap-4 overflow-y-auto sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>Edit prompt</SheetTitle>
                <SheetDescription>
                    Leave the title empty to use the first line of the body.
                </SheetDescription>
            </SheetHeader>

            <div class="grid gap-2">
                <Label for="prompt-title">Title</Label>
                <Input id="prompt-title" v-model="form.title" placeholder="Optional" />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="prompt-body">Prompt</Label>
                <textarea
                    id="prompt-body"
                    v-model="form.body"
                    rows="12"
                    class="border-input w-full rounded-md border bg-transparent p-2 font-mono text-sm outline-none"
                />
                <InputError :message="form.errors.body" />
            </div>

            <div class="grid gap-2">
                <Label>Project</Label>
                <Select v-model="form.project">
                    <SelectTrigger>
                        <SelectValue placeholder="Inbox" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="inbox">Inbox</SelectItem>
                        <SelectItem
                            v-for="project in projects"
                            :key="project.id"
                            :value="String(project.id)"
                        >
                            {{ project.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.project" />
            </div>

            <div class="grid gap-2">
                <Label>Status</Label>
                <Select v-model="form.status">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todo">Todo</SelectItem>
                        <SelectItem value="implementing">Implementing</SelectItem>
                        <SelectItem value="done">Done</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label>Priority</Label>
                <Select v-model="form.priority">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="low">Low</SelectItem>
                        <SelectItem value="normal">Normal</SelectItem>
                        <SelectItem value="high">High</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label>Tags</Label>
                <TagInput v-model="form.tags" />
            </div>

            <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                <Button variant="destructive" :disabled="form.processing" @click="destroy">
                    Delete
                </Button>
                <Button :disabled="form.processing" @click="save">Save</Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
