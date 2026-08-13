import type { ProjectColor } from '@/types';

export const PROJECT_DOT_CLASSES: Record<ProjectColor, string> = {
    slate: 'bg-slate-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
};

export const PROJECT_BORDER_CLASSES: Record<ProjectColor, string> = {
    slate: 'border-slate-500',
    rose: 'border-rose-500',
    amber: 'border-amber-500',
    emerald: 'border-emerald-500',
    sky: 'border-sky-500',
    violet: 'border-violet-500',
};

export const PROJECT_TEXT_CLASSES: Record<ProjectColor, string> = {
    slate: 'text-slate-500',
    rose: 'text-rose-500',
    amber: 'text-amber-500',
    emerald: 'text-emerald-500',
    sky: 'text-sky-500',
    violet: 'text-violet-500',
};

export const PROJECT_COLORS: ProjectColor[] = [
    'slate',
    'rose',
    'amber',
    'emerald',
    'sky',
    'violet',
];
