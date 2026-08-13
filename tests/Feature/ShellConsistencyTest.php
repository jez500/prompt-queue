<?php

/*
|--------------------------------------------------------------------------
| Shell consistency
|--------------------------------------------------------------------------
|
| The queue and settings screens drifted apart once already, because settings
| rendered inside a second, separately-styled shell. These tests pin the
| invariants that keep every authenticated screen on one shell: layouts are
| resolved centrally in app.ts, pages never bring their own chrome, and the
| retired starter-kit shell stays retired.
|
*/

function jsSource(string $relativePath): string
{
    $path = resource_path('js/'.$relativePath);

    expect($path)->toBeReadableFile();

    return (string) file_get_contents($path);
}

/**
 * @return array<int, string>
 */
function pageComponents(): array
{
    return glob(resource_path('js/pages/**/*.vue')) ?: [];
}

it('resolves every authenticated page to the queue shell', function (): void {
    $app = jsSource('app.ts');

    expect($app)
        ->toContain("case name.startsWith('settings/'):")
        ->toContain('return [PromptQueueLayout, SettingsLayout];')
        ->toContain('default:')
        ->toContain('return PromptQueueLayout;');
});

it('does not let pages declare their own layout component', function (): void {
    $offenders = [];

    foreach (pageComponents() as $page) {
        $relative = str_replace(resource_path('js/pages/'), '', $page);

        /* Auth screens are a separate, pre-login surface with their own shell. */
        if (str_starts_with($relative, 'auth/')) {
            continue;
        }

        if (str_contains((string) file_get_contents($page), 'layout:')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBeEmpty(
        'Pages must inherit the shell from app.ts, not declare their own: '
        .implode(', ', $offenders)
    );
});

it('keeps the retired starter-kit shell deleted', function (): void {
    $retired = [
        'layouts/AppLayout.vue',
        'layouts/app/AppSidebarLayout.vue',
        'layouts/app/AppHeaderLayout.vue',
        'components/AppSidebar.vue',
        'components/AppHeader.vue',
        'components/AppSidebarHeader.vue',
        'components/AppShell.vue',
        'components/AppContent.vue',
    ];

    foreach ($retired as $path) {
        expect(resource_path('js/'.$path))->not->toBeFile();
    }
});

it('builds both list panes on the shared pane primitives', function (): void {
    foreach (['components/prompts/PromptListPane.vue', 'components/settings/SettingsNavPane.vue'] as $pane) {
        expect(jsSource($pane))
            ->toContain('AppPane')
            ->toContain('NarrowTopBar');
    }
});

it('styles the shared shell from theme tokens rather than raw hex', function (): void {
    $shell = [
        'components/shell/AppPane.vue',
        'components/shell/PaneHeader.vue',
        'components/shell/NarrowTopBar.vue',
        'layouts/settings/Layout.vue',
        'layouts/prompts/PromptQueueLayout.vue',
    ];

    foreach ($shell as $path) {
        expect(jsSource($path))->not->toMatch('/#[0-9A-Fa-f]{6}\b/');
    }
});

it('declares both layout thresholds in one composable', function (): void {
    expect(jsSource('composables/useShellBreakpoints.ts'))
        ->toContain('(max-width: 1099px)')
        ->toContain('(min-width: 1100px) and (max-width: 1259px)');
});

it('does not let media queries be redeclared outside that composable', function (): void {
    $offenders = [];

    foreach ((array) glob(resource_path('js/{components,layouts,pages,composables}/**/*.{vue,ts}'), GLOB_BRACE) as $file) {
        $relative = str_replace(resource_path('js/'), '', $file);

        /* shadcn primitives carry their own unrelated breakpoint. */
        if (str_starts_with($relative, 'components/ui/') || str_contains($relative, 'useShellBreakpoints')) {
            continue;
        }

        if (str_contains((string) file_get_contents($file), 'useMediaQuery(')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBeEmpty(
        'Use useShellBreakpoints() so the shell agrees on its layout mode: '
        .implode(', ', $offenders)
    );
});

it('keeps the detail header on a single row', function (): void {
    /*
      The header wrapped to two lines between 1100px and 1260px. It stays on
      one row because it does not wrap and sheds labels in the compact band.
    */
    expect(jsSource('components/shell/PaneHeader.vue'))
        ->toContain('flex-nowrap')
        ->toContain('eyebrow && !compact');

    expect(jsSource('components/prompts/PromptDetailPane.vue'))
        ->toContain("compact.value ? 'Copy' : 'Copy prompt'");

    expect(jsSource('components/shell/AppPane.vue'))
        ->toContain("compact ? 'w-[344px]' : 'w-[430px]'");
});

it('gives every icon-only action both a tooltip and an accessible name', function (): void {
    $source = jsSource('components/prompts/PromptDetailPane.vue');

    expect($source)
        ->toContain('aria-label="Delete prompt"')
        ->toContain('<TooltipContent>Delete prompt</TooltipContent>')
        ->toContain(':aria-label="doneToggleLabel"')
        ->toContain('<TooltipContent>{{ doneToggleLabel }}</TooltipContent>')
        ->toContain('<Trash2 class="size-4" />')
        ->toContain('<Check v-else class="size-4" />')
        ->toContain('<RotateCcw');

    /* TooltipProvider is mounted once, at the shell root. */
    expect(jsSource('layouts/prompts/PromptQueueLayout.vue'))
        ->toContain('<TooltipProvider');
});

it('orders the detail metadata as status, priority, project', function (): void {
    /* Search the template only — the imports mention these in another order. */
    $template = substr(
        jsSource('components/prompts/PromptDetailPane.vue'),
        (int) strpos(jsSource('components/prompts/PromptDetailPane.vue'), '<template>')
    );

    $status = strpos($template, 'PROMPT_STATUS_QUEUE_PILL_CLASSES');
    $priority = strpos($template, 'PROMPT_PRIORITY_QUEUE_PILL_CLASSES');
    $project = strpos($template, ':href="projectHref"');

    expect($status)->toBeLessThan($priority)
        ->and($priority)->toBeLessThan($project);
});
