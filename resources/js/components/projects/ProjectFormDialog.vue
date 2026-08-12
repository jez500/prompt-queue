<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PROJECT_COLORS, PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import type { ProjectColor } from '@/types';

const open = ref(false);

const form = useForm<{ name: string; color: ProjectColor }>({
    name: '',
    color: 'slate',
});

const submit = (): void => {
    form.post(ProjectController.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>New project</DialogTitle>
                <DialogDescription class="sr-only">
                    Create a sidebar project for grouping prompts.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="project-name">Name</Label>
                <Input
                    id="project-name"
                    v-model="form.name"
                    placeholder="Prompt Queue"
                    @keydown.enter.prevent="submit"
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

            <div class="flex justify-end">
                <Button :disabled="form.processing" @click="submit">
                    Create
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
