<?php

/*
|--------------------------------------------------------------------------
| Installability
|--------------------------------------------------------------------------
|
| Installing on a phone fails silently: a manifest pointing at an icon that
| `npm run icons` never wrote, or a service worker that stopped registering,
| both leave the browser quietly refusing to offer the install prompt with
| nothing in the app to show for it. These tests pin the pieces that have to
| line up — the manifest's shape, that every icon it names is really on disk,
| and that the shell still links it all together.
|
*/

it('serves a manifest describing an installable app', function (): void {
    $response = $this->get(route('manifest'));

    $response->assertOk();

    expect($response->headers->get('Content-Type'))
        ->toContain('application/manifest+json');

    $response->assertJson([
        'name' => config('app.name'),
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
    ]);
});

it('does not put the manifest behind auth', function (): void {
    /* The browser fetches it on the login screen, before anyone signs in. */
    $this->get(route('manifest'))->assertOk();
});

it('offers the icon sizes Android installability requires', function (): void {
    $icons = collect($this->get(route('manifest'))->json('icons'));

    expect($icons->firstWhere('sizes', '192x192'))->not->toBeNull();

    $fullSize = $icons->where('sizes', '512x512');

    expect($fullSize->firstWhere('purpose', 'any'))->not->toBeNull();
    expect($fullSize->firstWhere('purpose', 'maskable'))->not->toBeNull(
        'A maskable icon is what stops Android cropping the mark into its own shape.'
    );
});

it('has really generated every icon the manifest points at', function (): void {
    $missing = collect($this->get(route('manifest'))->json('icons'))
        ->pluck('src')
        ->reject(fn (string $src): bool => is_file(public_path(ltrim($src, '/'))))
        ->all();

    expect($missing)->toBeEmpty(
        'The manifest names icons that are not in public/. Run `npm run icons`: '
        .implode(', ', $missing)
    );
});

it('ships the favicons the shell links to', function (): void {
    expect(public_path('favicon.svg'))->toBeReadableFile();
    expect(public_path('favicon.ico'))->toBeReadableFile();
    expect(public_path('apple-touch-icon.png'))->toBeReadableFile();
});

it('links the manifest and tints the browser chrome for both palettes', function (): void {
    $shell = (string) file_get_contents(resource_path('views/app.blade.php'));

    expect($shell)
        ->toContain("<link rel=\"manifest\" href=\"{{ route('manifest') }}\">")
        ->toContain('content="#fbfaf9" media="(prefers-color-scheme: light)"')
        ->toContain('content="#08080a" media="(prefers-color-scheme: dark)"')
        ->toContain('name="apple-mobile-web-app-capable"');
});

it('registers the service worker from the app entrypoint', function (): void {
    $entrypoint = (string) file_get_contents(resource_path('js/app.ts'));

    expect($entrypoint)->toContain('registerServiceWorker();');

    $module = (string) file_get_contents(resource_path('js/lib/serviceWorker.ts'));

    expect($module)
        ->toContain("navigator.serviceWorker.register('/sw.js')")
        ->toContain('import.meta.env.PROD');
});

it('keeps a fetch handler in the service worker, which is what makes it installable', function (): void {
    $worker = (string) file_get_contents(public_path('sw.js'));

    expect($worker)
        ->toContain("addEventListener('fetch'")
        ->toContain('/offline.html')
        /* Answering Inertia's XHR visits with HTML reads as corrupt data. */
        ->toContain("event.request.mode !== 'navigate'");

    expect(public_path('offline.html'))->toBeReadableFile();
});
