import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import PromptStatusController from '@/actions/App/Http/Controllers/PromptStatusController';
import type { Prompt } from '@/types';

/**
 * Copies a prompt body, then advances a fresh prompt to Implementing.
 *
 * The async Clipboard API needs a secure context, which a self-hosted
 * instance served over plain HTTP on a LAN is not. That is a normal way to
 * run this app rather than an edge case, so there is a real fallback:
 * select the body and copy it with the legacy command, and only ask the user
 * to press the shortcut themselves if even that is refused.
 */
export function useCopyPrompt() {
    const copy = async (prompt: Prompt): Promise<void> => {
        const clipboard = navigator.clipboard;

        if (clipboard && window.isSecureContext) {
            try {
                await clipboard.writeText(prompt.body);
                onCopied(prompt);

                return;
            } catch {
                // Blocked or unavailable — try the selection fallback below.
            }
        }

        if (copyViaSelection(prompt.id)) {
            onCopied(prompt);

            return;
        }

        toast.error('Copy failed — the text is selected, copy it manually.');
    };

    const onCopied = (prompt: Prompt): void => {
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

    /**
     * Select the rendered body and copy it with the legacy command.
     * Returns whether the copy actually happened; the text is left selected
     * either way so the user can finish the job by hand.
     */
    const copyViaSelection = (promptId: number): boolean => {
        const element = document.querySelector<HTMLElement>(
            `[data-prompt-body="${promptId}"]`,
        );

        if (!element) {
            return false;
        }

        if (
            element instanceof HTMLTextAreaElement ||
            element instanceof HTMLInputElement
        ) {
            /* A Range cannot select the value of a form control. */
            element.focus();
            element.select();
        } else {
            const range = document.createRange();
            range.selectNodeContents(element);
            window.getSelection()?.removeAllRanges();
            window.getSelection()?.addRange(range);
        }

        try {
            return document.execCommand('copy');
        } catch {
            return false;
        }
    };

    return { copy };
}
