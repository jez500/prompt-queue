import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useInitials } from '@/composables/useInitials';
import { PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import { index } from '@/routes/prompts';
import type { ProjectColor } from '@/types';

const NEUTRAL_DOT_CLASS = 'bg-[#5A5A66]';

export type ProjectScopeItem = {
    id: string;
    name: string;
    href: ReturnType<typeof index>;
    active: boolean;
    color: ProjectColor | null;
    dotClass: string;
    count: number | null;
    initials: string;
};

/**
 * Builds the "All prompts / projects / No project" nav rows shared by the
 * sidebar and the narrow-viewport topbar pill scroller.
 */
export function useProjectScopeNav() {
    const page = usePage();
    const { getInitials } = useInitials();

    const projects = computed(() => page.props.projects);

    const currentUrl = computed(() => new URL(page.url, 'http://localhost'));

    const currentProject = computed<string | null>(() =>
        currentUrl.value.searchParams.get('project'),
    );

    /**
     * Scope rows only ever highlight on the queue itself. Without this an
     * unscoped route such as /settings/profile has no `project` param and
     * would light up "All prompts" while the user is somewhere else entirely.
     */
    const onQueue = computed(() => currentUrl.value.pathname === index().url);

    const items = computed<ProjectScopeItem[]>(() => [
        {
            id: 'all',
            name: 'All prompts',
            href: index(),
            active: onQueue.value && currentProject.value === null,
            color: null,
            dotClass: NEUTRAL_DOT_CLASS,
            count: null,
            initials: getInitials('All prompts'),
        },
        ...projects.value.map((project) => ({
            id: String(project.id),
            name: project.name,
            href: index({ query: { project: String(project.id) } }),
            active:
                onQueue.value && currentProject.value === String(project.id),
            color: project.color,
            dotClass: PROJECT_DOT_CLASSES[project.color],
            count: project.openPromptsCount,
            initials: getInitials(project.name),
        })),
        {
            id: 'inbox',
            name: 'No project',
            href: index({ query: { project: 'inbox' } }),
            active: onQueue.value && currentProject.value === 'inbox',
            color: null,
            dotClass: NEUTRAL_DOT_CLASS,
            count: null,
            initials: getInitials('No project'),
        },
    ]);

    return { items, currentProject };
}
