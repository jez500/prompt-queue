<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SettingsNavPane from '@/components/settings/SettingsNavPane.vue';
import AppPane from '@/components/shell/AppPane.vue';
import PaneHeader from '@/components/shell/PaneHeader.vue';
import { useShellBreakpoints } from '@/composables/useShellBreakpoints';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const SECTION_TITLES: Record<string, string> = {
    [new URL(editProfile().url, 'http://localhost').pathname]: 'Profile',
    [new URL(editSecurity().url, 'http://localhost').pathname]: 'Security',
    [new URL(editAppearance().url, 'http://localhost').pathname]: 'Appearance',
};

const page = usePage();
const { narrow } = useShellBreakpoints();

/**
 * Below the narrow breakpoint the two panes share the viewport the same way
 * the queue does: the content pane is what you land on, and the back button
 * swaps to the nav list. Navigating to a section returns to the content.
 */
const showNav = ref(false);

watch(
    () => page.url,
    () => {
        showNav.value = false;
    },
);

const sectionTitle = computed(
    () =>
        SECTION_TITLES[new URL(page.url, 'http://localhost').pathname] ??
        'Settings',
);
</script>

<template>
    <SettingsNavPane v-if="!narrow || showNav" :narrow="narrow" />

    <AppPane v-if="!narrow || !showNav" variant="detail" :narrow="narrow">
        <PaneHeader
            :eyebrow="sectionTitle"
            :narrow="narrow"
            @back="showNav = true"
        />

        <div
            class="flex flex-1 flex-col overflow-y-auto"
            :class="narrow ? 'p-4' : 'px-[26px] py-[22px]'"
        >
            <section class="max-w-xl space-y-12">
                <slot />
            </section>
        </div>
    </AppPane>
</template>
