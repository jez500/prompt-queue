<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { onBeforeUnmount, onMounted, provide, ref } from 'vue';
import PromptQueueSidebar from '@/components/prompts/PromptQueueSidebar.vue';

const SIDEBAR_STORAGE_KEY = 'pq.sidebar';

const collapsed = ref(false);
const narrow = useMediaQuery('(max-width: 1099px)');
const newPromptSignal = ref(0);

provide('promptQueueNewPrompt', newPromptSignal);

const toggleSidebar = (): void => {
    collapsed.value = !collapsed.value;

    try {
        localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Storage unavailable — collapse state just won't persist.
    }
};

const requestNewPrompt = (): void => {
    newPromptSignal.value += 1;
};

let onKeydown: ((event: KeyboardEvent) => void) | null = null;

onMounted(() => {
    try {
        collapsed.value = localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    } catch {
        // Storage unavailable — default to expanded.
    }

    onKeydown = (event: KeyboardEvent): void => {
        const target = event.target as HTMLElement | null;
        const tag = target?.tagName;

        if (tag === 'INPUT' || tag === 'TEXTAREA') {
            return;
        }

        if (event.key === 'n' || event.key === 'N') {
            event.preventDefault();
            requestNewPrompt();
        }
    };

    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    if (onKeydown) {
        window.removeEventListener('keydown', onKeydown);
    }
});
</script>

<template>
    <div
        class="relative flex h-screen w-full overflow-hidden bg-background font-sans text-foreground"
    >
        <PromptQueueSidebar
            v-if="!narrow"
            :collapsed="collapsed"
            @toggle="toggleSidebar"
            @new-prompt="requestNewPrompt"
        />
        <slot />
    </div>
</template>
