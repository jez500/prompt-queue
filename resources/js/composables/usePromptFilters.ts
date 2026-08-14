import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed } from 'vue';
import { index } from '@/routes/prompts';
import type { PromptFilters } from '@/types';

type FilterValue = string | string[] | null;

/**
 * Build the next query string from the one in the address bar, changing only
 * the given keys.
 *
 * Deliberately not rebuilt from the `filters` prop: the server resolves
 * omitted filters to defaults (status becomes todo + implementing), so
 * echoing that prop back would write those defaults into the URL as if the
 * user had chosen them — which turns dragging off, because reordering is only
 * offered in an unfiltered bucket.
 */
const buildQuery = (next: Record<string, FilterValue>): string => {
    const params = new URLSearchParams(window.location.search);

    for (const [key, value] of Object.entries(next)) {
        for (const existing of [...params.keys()]) {
            if (existing === key || existing.startsWith(`${key}[`)) {
                params.delete(existing);
            }
        }

        /* Inlined rather than extracted, so the null check narrows below. */
        if (
            value === null ||
            value === '' ||
            (Array.isArray(value) && value.length === 0)
        ) {
            continue;
        }

        if (Array.isArray(value)) {
            value.forEach((entry, position) =>
                params.append(`${key}[${position}]`, entry),
            );

            continue;
        }

        params.set(key, value);
    }

    return params.toString();
};

export function usePromptFilters(current: () => PromptFilters) {
    const filters = computed(current);

    const visit = (
        next: Record<string, FilterValue>,
        only: string[] = ['prompts', 'selected', 'filters', 'canReorder'],
    ): void => {
        const query = buildQuery(next);

        router.get(
            index.url() + (query === '' ? '' : `?${query}`),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only,
            },
        );
    };

    const setFilter = (key: keyof PromptFilters, value: FilterValue): void => {
        /* Changing what is listed can strand the open prompt off the list. */
        visit({ [key]: value, prompt: null });
    };

    /**
     * Open a prompt in the editor. Selection lives in the URL so the body is
     * fetched for that one prompt, and so a link to it opens it directly.
     */
    const selectPrompt = (id: number | null): void => {
        visit({ prompt: id === null ? null : String(id) }, ['selected']);
    };

    /**
     * Re-scope the list to the project a prompt was just moved into, keeping
     * that prompt open.
     *
     * Unlike `setFilter` this holds on to `prompt`: the move is the reason the
     * list changed, and dropping the selection would close the editor the move
     * was made from.
     */
    const followPrompt = (projectId: number | null, promptId: number): void => {
        visit({
            project: projectId === null ? 'inbox' : String(projectId),
            prompt: String(promptId),
        });
    };

    const search = useDebounceFn((term: string): void => {
        visit({ q: term, prompt: null });
    }, 250);

    return { filters, setFilter, search, selectPrompt, followPrompt };
}
