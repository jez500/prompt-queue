<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PROJECT_DOT_CLASSES } from '@/lib/projectColors';

/**
 * The project a prompt is filed under, as a menu.
 *
 * Presentation only: it reports the chosen project and leaves persisting to
 * the caller. `null` is the Inbox, which the menu spells "No project" — the
 * radio group needs a string, so it travels as `inbox`.
 */
const { modelValue } = defineProps<{ modelValue: number | null }>();

const emit = defineEmits<{ 'update:modelValue': [projectId: number | null] }>();

const INBOX = 'inbox';

const page = usePage();

const project = computed(
    () =>
        page.props.projects.find((candidate) => candidate.id === modelValue) ??
        null,
);

const select = (value: AcceptableValue): void => {
    if (typeof value !== 'string') {
        return;
    }

    const projectId = value === INBOX ? null : Number(value);

    if (projectId !== modelValue) {
        emit('update:modelValue', projectId);
    }
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                aria-label="Change project"
                class="flex items-center gap-1.5 text-[12.5px] text-muted-foreground hover:text-foreground"
                @click.stop
            >
                <span
                    class="size-1.5 rounded-full"
                    :class="
                        project
                            ? PROJECT_DOT_CLASSES[project.color]
                            : 'bg-faint-foreground'
                    "
                />
                <span>{{ project?.name ?? 'No project' }}</span>
                <span class="text-[8px] opacity-70">▾</span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="start"
            class="w-48 border-ring bg-popover text-popover-foreground"
        >
            <DropdownMenuRadioGroup
                :model-value="modelValue === null ? INBOX : String(modelValue)"
                @update:model-value="select"
            >
                <DropdownMenuRadioItem
                    :value="INBOX"
                    class="gap-2 pl-2 text-muted-foreground focus:bg-surface-hover focus:text-foreground data-[state=checked]:text-foreground"
                >
                    <template #indicator-icon><span /></template>
                    <span class="size-1.5 rounded-full bg-faint-foreground" />
                    No project
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem
                    v-for="candidate in page.props.projects"
                    :key="candidate.id"
                    :value="String(candidate.id)"
                    class="gap-2 pl-2 text-muted-foreground focus:bg-surface-hover focus:text-foreground data-[state=checked]:text-foreground"
                >
                    <template #indicator-icon><span /></template>
                    <span
                        class="size-1.5 rounded-full"
                        :class="PROJECT_DOT_CLASSES[candidate.color]"
                    />
                    <span class="truncate">{{ candidate.name }}</span>
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
