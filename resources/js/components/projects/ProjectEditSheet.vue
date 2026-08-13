<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { PROJECT_COLORS, PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import type { Project, ProjectColor } from '@/types';

const { project } = defineProps<{ project: Project | null }>();

const emit = defineEmits<{ close: [] }>();

const form = useForm<{ name: string; color: ProjectColor }>({
    name: '',
    color: 'slate',
});

watch(
    () => project,
    (value) => {
        if (!value) {
            return;
        }

        form.defaults({
            name: value.name,
            color: value.color,
        });
        form.reset();
        form.clearErrors();
        confirmingDelete.value = false;
    },
    { immediate: true },
);

/* Deletion sits beside Save, so it asks first and says what will happen. */
const confirmingDelete = ref(false);

const save = (): void => {
    if (!project) {
        return;
    }

    form.patch(ProjectController.update.url({ project: project.id }), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};

const destroy = (): void => {
    if (!project) {
        return;
    }

    confirmingDelete.value = false;

    form.delete(ProjectController.destroy.url({ project: project.id }), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Sheet
        :open="project !== null"
        @update:open="(open: boolean) => !open && emit('close')"
    >
        <SheetContent
            class="flex w-full flex-col gap-4 overflow-y-auto px-6 py-6 sm:max-w-lg"
        >
            <SheetHeader class="p-0">
                <SheetTitle>Edit project</SheetTitle>
                <SheetDescription>
                    Update the project name and colour, or delete it.
                </SheetDescription>
            </SheetHeader>

            <div class="grid gap-2">
                <Label for="project-edit-name">Name</Label>
                <Input
                    id="project-edit-name"
                    v-model="form.name"
                    placeholder="Project name"
                    @keydown.enter.prevent="save"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label>Colour</Label>
                <div class="flex gap-2">
                    <button
                        v-for="color in PROJECT_COLORS"
                        :key="color"
                        type="button"
                        :aria-label="color"
                        class="size-6 rounded-full ring-offset-2 ring-offset-background"
                        :class="[
                            PROJECT_DOT_CLASSES[color],
                            form.color === color ? 'ring-2 ring-ring' : '',
                        ]"
                        @click="form.color = color"
                    />
                </div>
                <InputError :message="form.errors.color" />
            </div>

            <div
                v-if="confirmingDelete"
                class="mt-auto flex flex-col gap-3 rounded-lg border border-border bg-muted p-4"
            >
                <div class="space-y-1">
                    <p class="text-sm font-medium">Delete this project?</p>
                    <p class="text-sm text-muted-foreground">
                        Its prompts are kept and moved to No project.
                    </p>
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        variant="secondary"
                        :disabled="form.processing"
                        @click="confirmingDelete = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="form.processing"
                        @click="destroy"
                    >
                        Delete project
                    </Button>
                </div>
            </div>

            <div
                v-else
                class="mt-auto flex items-center justify-between gap-2 pt-4"
            >
                <Button
                    variant="destructive"
                    :disabled="form.processing"
                    @click="confirmingDelete = true"
                >
                    Delete
                </Button>
                <Button :disabled="form.processing" @click="save">Save</Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
