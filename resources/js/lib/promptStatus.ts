import type { PromptStatus } from '@/types';

/**
 * Every status, in the order they are offered in menus and filters.
 * Declared once so a new case cannot reach some lists and miss others.
 */
export const PROMPT_STATUSES: PromptStatus[] = ['todo', 'implementing', 'done'];

export const PROMPT_STATUS_LABELS: Record<PromptStatus, string> = {
    todo: 'Todo',
    implementing: 'Implementing',
    done: 'Done',
};

export const PROMPT_STATUS_BADGE_CLASSES: Record<PromptStatus, string> = {
    todo: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200',
    implementing:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/70 dark:text-sky-200',
    done: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200',
};

export const PROMPT_STATUS_FILTER_ACTIVE_CLASSES: Record<PromptStatus, string> =
    {
        todo: 'ring-1 ring-slate-300 dark:ring-slate-600',
        implementing: 'ring-1 ring-sky-300 dark:ring-sky-700',
        done: 'ring-1 ring-emerald-300 dark:ring-emerald-700',
    };

/**
 * Pill colours for the prompt queue's status dropdown trigger, independent
 * of the light-mode badge classes above so the rest of the app is unaffected.
 */
export const PROMPT_STATUS_QUEUE_PILL_CLASSES: Record<PromptStatus, string> = {
    todo: 'bg-[#1B1B22] text-[#9A9AA6]',
    implementing: 'bg-[#3B82F6]/16 text-[#7FB2FF]',
    done: 'bg-[#2E7D5B]/18 text-[#6FCFA1]',
};

/**
 * Dot colours used inside the status dropdown menu and next to the label.
 */
export const PROMPT_STATUS_QUEUE_DOT_CLASSES: Record<PromptStatus, string> = {
    todo: 'bg-[#8A8A96]',
    implementing: 'bg-[#3B82F6]',
    done: 'bg-[#2E7D5B]',
};
