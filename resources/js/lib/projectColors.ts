import type { ProjectColor } from '@/types';

export const PROJECT_DOT_CLASSES: Record<ProjectColor, string> = {
    slate: 'bg-slate-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
};

export const PROJECT_COLORS: ProjectColor[] = [
    'slate',
    'rose',
    'amber',
    'emerald',
    'sky',
    'violet',
];
