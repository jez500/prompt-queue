import { useMediaQuery } from '@vueuse/core';
import type { Ref } from 'vue';

/**
 * The shell's two layout thresholds, defined in one place so the sidebar,
 * panes and pane headers can never disagree about which mode they're in.
 *
 * `narrow` drops the sidebar and switches the panes to a single-column
 * master-detail flow. `compact` is the band just above that, where all three
 * columns are on screen but the detail pane is tight enough that a roomy
 * header would wrap — labels shrink and the list pane gives back some width.
 */
export type ShellBreakpoints = {
    narrow: Ref<boolean>;
    compact: Ref<boolean>;
};

export function useShellBreakpoints(): ShellBreakpoints {
    return {
        narrow: useMediaQuery('(max-width: 1099px)'),
        compact: useMediaQuery('(min-width: 1100px) and (max-width: 1259px)'),
    };
}
