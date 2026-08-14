<?php

/*
|--------------------------------------------------------------------------
| Theme palettes
|--------------------------------------------------------------------------
|
| The app used to render dark whatever the Appearance setting said, because
| `:root` and `.dark` shared one palette — the toggle worked and changed
| nothing. There are two palettes now: the warm paper one from the redesign's
| light variant in `:root`, and the original dark one in `.dark`.
|
| These cases guard the wiring rather than the taste: that both blocks exist,
| that they disagree, and that every token one defines the other defines too.
| A token present in only one theme is invisible until someone switches.
|
*/
function themeBlock(string $selector): string
{
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $start = strpos($css, $selector."\n{") !== false
        ? strpos($css, $selector."\n{")
        : strpos($css, $selector.' {');

    expect($start)->not->toBeFalse("The {$selector} palette block is gone.");

    $end = strpos($css, "\n}", (int) $start);

    return substr($css, (int) $start, (int) $end - (int) $start);
}

/**
 * @return array<string, string>
 */
function themeTokens(string $selector): array
{
    preg_match_all('/(--[a-z0-9-]+):\s*([^;]+);/i', themeBlock($selector), $matches, PREG_SET_ORDER);

    $tokens = [];

    foreach ($matches as $match) {
        $tokens[$match[1]] = trim($match[2]);
    }

    expect($tokens)->not->toBeEmpty("No tokens found in {$selector}.");

    return $tokens;
}

it('gives light and dark their own palettes', function (): void {
    $light = themeTokens(':root');
    $dark = themeTokens('.dark');

    expect($light['--background'])->toBe('#fbfaf9')
        ->and($dark['--background'])->toBe('#08080a')
        ->and($light['--foreground'])->toBe('#1b1a18')
        ->and($dark['--foreground'])->toBe('#f2f2f4');
});

it('defines every token in both themes', function (): void {
    $light = array_keys(themeTokens(':root'));
    $dark = array_keys(themeTokens('.dark'));

    sort($light);
    sort($dark);

    expect($light)->toBe($dark);
});

it('keeps the two surface ramps apart', function (): void {
    /*
      Sharing a value between the themes is how the old dark-only palette
      looked like it worked. Only genuinely theme-independent values — pure
      white, the radius — are allowed to match.
    */
    $light = themeTokens(':root');
    $dark = themeTokens('.dark');
    $shared = ['--primary-foreground', '--destructive-foreground', '--sidebar-primary-foreground', '--radius'];

    foreach ($light as $token => $value) {
        if (in_array($token, $shared, true)) {
            continue;
        }

        expect($value)->not->toBe(
            $dark[$token],
            "{$token} is the same in both themes — switching Appearance would not change it."
        );
    }
});

it('paints the first-paint background from the same palette', function (): void {
    /*
      The blade shell sets an inline html background so the page does not
      flash the wrong colour before app.css lands. It has to track the
      palette by hand.
    */
    expect((string) file_get_contents(resource_path('views/app.blade.php')))
        ->toContain('background-color: #fbfaf9;')
        ->toContain('background-color: #08080a;');
});

it('renders on the client only, so the shell can size itself from the viewport', function (): void {
    /*
      The shell picks its layout from `useShellBreakpoints`, which a server
      with no viewport resolves to the widest one. Vue refuses to rectify the
      attributes that mismatch on hydration, so a narrow window loaded the
      desktop widths and kept them. Nothing builds an SSR bundle anyway.
    */
    expect(config('inertia.ssr.enabled'))->toBeFalse();
});
