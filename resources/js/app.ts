import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PromptQueueLayout from '@/layouts/prompts/PromptQueueLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Prompt Queue';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    /*
      Everything behind auth renders inside the queue shell. Making it the
      default (rather than something pages opt into) is what stops a new page
      from quietly growing its own chrome.
    */
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [PromptQueueLayout, SettingsLayout];
            default:
                return PromptQueueLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
