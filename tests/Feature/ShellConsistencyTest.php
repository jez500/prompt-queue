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
