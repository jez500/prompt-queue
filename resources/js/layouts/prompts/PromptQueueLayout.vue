<script setup lang="ts">
import { onMounted, provide, ref } from 'vue';
import PromptQueueSidebar from '@/components/prompts/PromptQueueSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { useShellBreakpoints } from '@/composables/useShellBreakpoints';

const SIDEBAR_STORAGE_KEY = 'pq.sidebar';

const collapsed = ref(false);
const { narrow } = useShellBreakpoints();
const newPromptSignal = ref(0);
const searchSignal = ref(0);
const copySignal = ref(0);

provide('promptQueueNewPrompt', newPromptSignal);
provide('promptQueueSearch', searchSignal);
provide('promptQueueCopy', copySignal);

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

useKeyboardShortcuts({
    new: requestNewPrompt,
    search: () => {
        searchSignal.value += 1;
    },
    copy: () => {
        /*
          Only claim the clipboard chord when nothing is selected — otherwise
          the browser's own copy of the highlighted text is the right result.
        */
        if ((window.getSelection()?.toString() ?? '') !== '') {
            return false;
        }

        copySignal.value += 1;
    },
});

onMounted(() => {
    try {
        collapsed.value = localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    } catch {
        // Storage unavailable — default to expanded.
    }
});
</script>

<template>
    <TooltipProvider :delay-duration="300">
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

            <!-- Mounted once, at the shell root: without it every toast()
                 call in the app is a silent no-op. -->
            <Toaster position="bottom-right" :duration="4000" />
        </div>
    </TooltipProvider>
</template>
