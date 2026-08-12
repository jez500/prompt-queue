import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import PromptStatusController from '@/actions/App/Http/Controllers/PromptStatusController';
import type { Prompt } from '@/types';

export function useCopyPrompt() {
    const copy = async (prompt: Prompt): Promise<void> => {
        const clipboard = navigator.clipboard;

        if (!clipboard || !window.isSecureContext) {
            selectBody(prompt.id);
            toast.error('Clipboard unavailable — the text is selected, copy it manually.');

            return;
        }

        try {
            await clipboard.writeText(prompt.body);
        } catch {
            selectBody(prompt.id);
            toast.error('Copy failed — the text is selected, copy it manually.');

            return;
        }

        toast.success('Copied to clipboard.');

        if (prompt.status !== 'todo') {
            return;
        }

        router.patch(
            PromptStatusController.url({ prompt: prompt.id }),
            {},
            { preserveScroll: true, preserveState: true, only: ['prompts'] },
        );
    };

    const selectBody = (promptId: number): void => {
        const element = document.querySelector<HTMLElement>(
            `[data-prompt-body="${promptId}"]`,
        );

        if (!element) {
            return;
        }

        const range = document.createRange();
        range.selectNodeContents(element);
        window.getSelection()?.removeAllRanges();
        window.getSelection()?.addRange(range);
    };

    return { copy };
}
