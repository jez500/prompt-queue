import type { PromptPriority } from '@/types';

/**
 * Every priority, highest first — the order they are offered in menus and
 * filters. Declared once so a new case cannot reach some lists and miss others.
 */
export const PROMPT_PRIORITIES: PromptPriority[] = ['high', 'normal', 'low'];

export const PROMPT_PRIORITY_LABELS: Record<PromptPriority, string> = {
    high: 'High',
    normal: 'Normal',
    low: 'Low',
};

export const PROMPT_PRIORITY_BADGE_CLASSES: Record<PromptPriority, string> = {
    high: 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/70 dark:text-red-200',
    normal: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-950/70 dark:text-orange-200',
    low: 'border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-800 dark:bg-yellow-950/70 dark:text-yellow-200',
};

export const PROMPT_PRIORITY_FILTER_ACTIVE_CLASSES: Record<
    PromptPriority,
    string
> = {
    high: 'ring-1 ring-red-300 dark:ring-red-700',
    normal: 'ring-1 ring-orange-300 dark:ring-orange-700',
    low: 'ring-1 ring-yellow-300 dark:ring-yellow-700',
};

/**
 * Pill colours for the prompt queue's priority dropdown trigger, independent
 * of the badge classes above so the rest of the app is unaffected.
 *
 * One pair per theme: the dark tints are washes over a near-black surface and
 * lose all their contrast on paper.
 */
export const PROMPT_PRIORITY_QUEUE_PILL_CLASSES: Record<
    PromptPriority,
    string
> = {
    high: 'text-[#C43350] bg-[#FBE9EC] dark:text-[#FF8B9C] dark:bg-[#FF4D6D]/15',
    /* Normal is the resting state and reads as neutral; the amber belongs to
       low, which is a deliberate choice the queue should show. */
    normal: 'text-[#6B675F] bg-[#F1EEEA] dark:text-[#8A8A96] dark:bg-[#17171E]',
    low: 'text-[#8A6A22] bg-[#F7EFDE] dark:text-[#EAB35F] dark:bg-[#EAB35F]/13',
};
