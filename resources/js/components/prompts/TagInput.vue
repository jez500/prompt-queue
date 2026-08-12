<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

const model = defineModel<string[]>({ required: true });

const page = usePage();
const draft = ref('');

const suggestions = computed(() =>
    page.props.tags.filter((tag) => !model.value.includes(tag)),
);

const add = (): void => {
    const name = draft.value.trim();

    if (name === '' || model.value.includes(name)) {
        draft.value = '';

        return;
    }

    model.value = [...model.value, name];
    draft.value = '';
};

const remove = (name: string): void => {
    model.value = model.value.filter((tag) => tag !== name);
};
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="model.length > 0" class="flex flex-wrap gap-1">
            <Badge v-for="tag in model" :key="tag" variant="secondary">
                #{{ tag }}
                <button
                    type="button"
                    class="ml-1"
                    :aria-label="`Remove ${tag}`"
                    @click="remove(tag)"
                >
                    <X class="size-3" />
                </button>
            </Badge>
        </div>

        <Input
            v-model="draft"
            list="tag-suggestions"
            placeholder="Add a tag and press Enter"
            @keydown.enter.prevent="add"
        />

        <datalist id="tag-suggestions">
            <option v-for="tag in suggestions" :key="tag" :value="tag" />
        </datalist>
    </div>
</template>
