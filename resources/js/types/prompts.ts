export type PromptStatus = 'todo' | 'implementing' | 'done';

export type PromptPriority = 'low' | 'normal' | 'high';

export type ProjectColor =
    | 'slate'
    | 'rose'
    | 'amber'
    | 'emerald'
    | 'sky'
    | 'violet';

export type Prompt = {
    id: number;
    title: string;
    rawTitle: string | null;
    body: string;
    status: PromptStatus;
    priority: PromptPriority;
    position: number;
    projectId: number | null;
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
