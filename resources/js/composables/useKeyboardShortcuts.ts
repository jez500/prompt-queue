import { onBeforeUnmount, onMounted } from 'vue';

/**
 * The app's keyboard shortcuts, declared in one place.
 *
 * The hint chips in the UI render from these definitions, so a hint cannot
 * advertise a binding that does not exist — which is how ⌘K and ⌘C came to be
 * shown for two features nothing had implemented.
 */
export type ShortcutId = 'new' | 'search' | 'copy';

type Shortcut = {
    id: ShortcutId;
    /** Rendered in the UI next to the control it drives. */
    hint: string;
    key: string;
    /** Whether Cmd (macOS) or Ctrl must be held. */
    modifier: boolean;
    /** Whether the binding still applies while typing in a field. */
    whileTyping: boolean;
};

const isApple = (): boolean =>
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.platform);

export const SHORTCUTS: Record<ShortcutId, Shortcut> = {
    new: {
        id: 'new',
        hint: 'N',
        key: 'n',
        modifier: false,
        whileTyping: false,
    },
    search: {
        id: 'search',
        hint: '⌘K',
        key: 'k',
        modifier: true,
        whileTyping: true,
    },
    copy: {
        id: 'copy',
        hint: '⌘C',
        key: 'c',
        modifier: true,
        whileTyping: false,
    },
};

/**
 * The label to show for a shortcut, with the platform's modifier symbol.
 */
export function shortcutHint(id: ShortcutId): string {
    const shortcut = SHORTCUTS[id];

    if (!shortcut.modifier) {
        return shortcut.hint;
    }

    return isApple() ? shortcut.hint : shortcut.hint.replace('⌘', 'Ctrl+');
}

const isTypingIn = (target: EventTarget | null): boolean => {
    const element = target as HTMLElement | null;
    const tag = element?.tagName;

    return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        element?.isContentEditable === true
    );
};

const matches = (event: KeyboardEvent, shortcut: Shortcut): boolean => {
    if (event.key.toLowerCase() !== shortcut.key) {
        return false;
    }

    const modifierHeld = event.metaKey || event.ctrlKey;

    if (shortcut.modifier) {
        return modifierHeld && !event.altKey && !event.shiftKey;
    }

    /*
      An unmodified letter must not fire while a chord is held, or Cmd+N and
      Ctrl+N are swallowed from the browser.
    */
    return !modifierHeld && !event.altKey;
};

/**
 * Bind the given handlers for as long as the calling component is mounted.
 * A handler that returns false declines the event, leaving the default alone.
 */
export function useKeyboardShortcuts(
    handlers: Partial<Record<ShortcutId, () => boolean | void>>,
): void {
    const onKeydown = (event: KeyboardEvent): void => {
        const typing = isTypingIn(event.target);

        for (const shortcut of Object.values(SHORTCUTS)) {
            const handler = handlers[shortcut.id];

            if (!handler || !matches(event, shortcut)) {
                continue;
            }

            if (typing && !shortcut.whileTyping) {
                continue;
            }

            if (handler() === false) {
                continue;
            }

            event.preventDefault();

            return;
        }
    };

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
}
