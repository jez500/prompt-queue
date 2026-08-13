import { router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import PromptController from '@/actions/App/Http/Controllers/PromptController';
import PromptPriorityController from '@/actions/App/Http/Controllers/PromptPriorityController';
import PromptStatusController from '@/actions/App/Http/Controllers/PromptStatusController';
import type { Prompt, PromptPriority, PromptStatus } from '@/types';

type Snapshot = { title: string; body: string };

type Options = {
    prompt: () => Prompt | null;
    isNew: () => boolean;
    projectId: () => number | null;
    onCreated: (id: number) => void;
};

/**
 * Owns the detail pane's editable fields and autosaves them: while `isNew()`
 * it waits for a non-blank body before POSTing (the store endpoint requires
 * one), then patches the title in if one was already typed. Once persisted,
 * further edits debounce into PATCH requests, matching the mock's
 * "Auto-saves / Saving… / Saved" affordance but tied to real request state.
 */
export function usePromptAutosave(options: Options) {
    const { prompt, isNew, projectId, onCreated } = options;
    const page = usePage();

    const title = ref('');
    const body = ref('');
    const status = ref<PromptStatus>('todo');
    const priority = ref<PromptPriority>('normal');
    const saving = ref(false);
    const savedAt = ref<number | null>(null);
    const creating = ref(false);

    const lastSaved = ref<Snapshot>({ title: '', body: '' });
    const justCreatedId = ref<number | null>(null);
    const justCreatedSnapshot = ref<Snapshot>({ title: '', body: '' });
    const editedId = ref<number | null>(null);

    const current = computed(() => prompt());

    const knownPrompts = (): Prompt[] =>
        (page.props.prompts as Prompt[] | undefined) ?? [];

    watch(
        current,
        (value) => {
            const incomingId = value?.id ?? null;
            const switchedPrompt = incomingId !== editedId.value;

            if (value && value.id === justCreatedId.value) {
                // We just created this prompt ourselves — don't clobber
                // edits made while the create request was in flight.
                lastSaved.value = justCreatedSnapshot.value;
                justCreatedId.value = null;
                editedId.value = incomingId;

                return;
            }

            // Status and priority are chosen from dropdowns rather than typed,
            // so following the server costs nobody their caret position.
            status.value = value?.status ?? 'todo';
            priority.value = value?.priority ?? 'normal';

            if (!switchedPrompt) {
                /*
                  Same prompt, refreshed data — nearly always the echo of our
                  own autosave. Adopting it would replace whatever has been
                  typed since the request went out, and because the string
                  differs Vue rewrites the textarea and throws the caret to the
                  end. Only take the server's copy when nothing is pending.
                */
                const hasPendingEdits =
                    title.value !== lastSaved.value.title ||
                    body.value !== lastSaved.value.body;

                if (hasPendingEdits) {
                    return;
                }
            }

            title.value = value?.rawTitle ?? '';
            body.value = value?.body ?? '';
            lastSaved.value = { title: title.value, body: body.value };
            editedId.value = incomingId;

            if (switchedPrompt) {
                savedAt.value = null;
            }
        },
        { immediate: true },
    );

    const patch = (): void => {
        const target = current.value;

        if (!target) {
            return;
        }

        if (
            title.value === lastSaved.value.title &&
            body.value === lastSaved.value.body
        ) {
            return;
        }

        saving.value = true;
        lastSaved.value = { title: title.value, body: body.value };

        router.patch(
            PromptController.update.url({ prompt: target.id }),
            {
                title: title.value.trim() === '' ? null : title.value,
                body: body.value,
                status: target.status,
                priority: target.priority,
                project: target.projectId,
                tags: target.tags,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['prompts'],
                onFinish: () => {
                    saving.value = false;
                    savedAt.value = Date.now();
                },
            },
        );
    };

    const create = (): void => {
        if (creating.value || body.value.trim() === '') {
            return;
        }

        const pendingTitle = title.value.trim();
        const sentSnapshot: Snapshot = { title: title.value, body: body.value };
        const knownIds = new Set(knownPrompts().map((entry) => entry.id));

        creating.value = true;
        saving.value = true;

        router.post(
            PromptController.store.url(),
            {
                body: body.value,
                status: status.value,
                priority: priority.value,
                project: projectId(),
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['prompts', 'projects', 'canReorder'],
                onSuccess: () => {
                    const created = knownPrompts().find(
                        (entry) => !knownIds.has(entry.id),
                    );

                    creating.value = false;

                    if (!created) {
                        saving.value = false;

                        return;
                    }

                    justCreatedId.value = created.id;
                    justCreatedSnapshot.value = sentSnapshot;

                    if (pendingTitle === '') {
                        saving.value = false;
                        savedAt.value = Date.now();
                        onCreated(created.id);

                        return;
                    }

                    router.patch(
                        PromptController.update.url({ prompt: created.id }),
                        { title: pendingTitle },
                        {
                            preserveScroll: true,
                            preserveState: true,
                            only: ['prompts'],
                            onFinish: () => {
                                saving.value = false;
                                savedAt.value = Date.now();
                                onCreated(created.id);
                            },
                        },
                    );
                },
                onError: () => {
                    creating.value = false;
                    saving.value = false;
                },
            },
        );
    };

    const debouncedSave = useDebounceFn((): void => {
        if (isNew()) {
            create();

            return;
        }

        patch();
    }, 500);

    watch([title, body], () => {
        if (isNew() && body.value.trim() === '') {
            return;
        }

        debouncedSave();
    });

    const setStatus = (value: PromptStatus): void => {
        status.value = value;

        if (isNew()) {
            return;
        }

        const target = current.value;

        if (!target || value === target.status) {
            return;
        }

        router.patch(
            PromptStatusController.url({ prompt: target.id }),
            { status: value },
            { preserveScroll: true, preserveState: true, only: ['prompts'] },
        );
    };

    const setPriority = (value: PromptPriority): void => {
        priority.value = value;

        if (isNew()) {
            return;
        }

        const target = current.value;

        if (!target || value === target.priority) {
            return;
        }

        router.patch(
            PromptPriorityController.url({ prompt: target.id }),
            { priority: value },
            { preserveScroll: true, preserveState: true, only: ['prompts'] },
        );
    };

    const updateTags = (tags: string[]): void => {
        const target = current.value;

        if (!target) {
            return;
        }

        router.patch(
            PromptController.update.url({ prompt: target.id }),
            {
                title: target.rawTitle,
                body: target.body,
                status: target.status,
                priority: target.priority,
                project: target.projectId,
                tags,
            },
            { preserveScroll: true, preserveState: true, only: ['prompts'] },
        );
    };

    const destroy = (): void => {
        const target = current.value;

        if (!target) {
            return;
        }

        router.delete(PromptController.destroy.url({ prompt: target.id }), {
            preserveScroll: true,
        });
    };

    return {
        title,
        body,
        status,
        priority,
        saving,
        savedAt,
        setStatus,
        setPriority,
        updateTags,
        destroy,
    };
}
