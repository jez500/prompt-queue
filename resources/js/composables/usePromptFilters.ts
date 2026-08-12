import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed } from 'vue';
import { index } from '@/routes/prompts';
import type { PromptFilters } from '@/types';

type FilterValue = string | string[] | null;

export function usePromptFilters(current: () => PromptFilters) {
    const filters = computed(current);

    const visit = (next: Record<string, FilterValue>): void => {
        const query: Record<string, FilterValue> = {
            project: filters.value.project,
            q: filters.value.q,
            status: filters.value.status,
            priority: filters.value.priority,
            tags: filters.value.tags,
            ...next,
        };

        Object.keys(query).forEach((key) => {
            const value = query[key];

            if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
                delete query[key];
            }
        });

        router.get(index.url(), query, {
            preserveState: true,
            preserveScroll: true,
            only: ['prompts', 'filters', 'canReorder'],
        });
    };

    const setFilter = (key: keyof PromptFilters, value: FilterValue): void => {
        visit({ [key]: value });
    };

    const search = useDebounceFn((term: string): void => {
        visit({ q: term });
    }, 250);

    return { filters, setFilter, search };
}
