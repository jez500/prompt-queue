export type PromptStatus = 'todo' | 'implementing' | 'done';

export type PromptPriority = 'low' | 'normal' | 'high';

export type ProjectColor =
    'slate' | 'rose' | 'amber' | 'emerald' | 'sky' | 'violet';

export type Prompt = {
    id: number;
    title: string;
    rawTitle: string | null;
    /** One-line preview for the list; the full body is not sent with it. */
    excerpt: string;
    /** Only present on the selected prompt — see PromptResource::withBody(). */
    body?: string;
    status: PromptStatus;
    priority: PromptPriority;
    position: number;
    projectId: number | null;
    projectName: string | null;
    tags: string[];
    updatedAt: string | null;
};

export type Project = {
    id: number;
    name: string;
    color: ProjectColor;
    openPromptsCount: number;
};

export type PromptFilters = {
    project: string | null;
    q: string | null;
    status: PromptStatus[];
    priority: PromptPriority[];
    tags: string[];
};
