<?php

/*
|--------------------------------------------------------------------------
| Reverse proxy
|--------------------------------------------------------------------------
|
| The container serves plain HTTP and TLS is terminated at a proxy, so every
| real deployment reaches Laravel over http with the original scheme in
| X-Forwarded-Proto. Unless that header is trusted, generated URLs come out as
| http:// on a page the browser loaded over https, and it blocks them as mixed
| content — stylesheets, scripts, fonts and the manifest all silently fail.
|
| Two things make this awkward to test, and both are why the requests below
| name an absolute http:// URL:
|
|   - Laravel's test helpers build request URLs from config('app.url'), which
|     is https here, so a plain $this->get() is already secure and proves
|     nothing.
|   - The assertion is on the manifest link because route() is deterministic.
|     @vite emits the dev-server URL whenever public/hot exists, which would
|     make this pass or fail depending on whether npm run dev is running.
|
*/

test('the shell links the manifest over https behind a tls-terminating proxy', function () {
    $response = $this->get('http://localhost/login', ['X-Forwarded-Proto' => 'https']);

    $response->assertOk();

    expect($response->getContent())
        ->toContain('href="https://localhost/manifest.webmanifest"');
});

test('the same request without the header stays on http', function () {
    $response = $this->get('http://localhost/login');

    $response->assertOk();

    /* The control. Without it the test above could pass because everything is
       https already, rather than because the header was honoured. */
    expect($response->getContent())
        ->toContain('href="http://localhost/manifest.webmanifest"');
});

test('asset urls follow the forwarded scheme', function () {
    $this->get('http://localhost/login', ['X-Forwarded-Proto' => 'https']);

    /* The reported symptom was blocked scripts, stylesheets and fonts. Those
       come from @vite via asset(), which cannot be asserted through the shell
       here because public/hot makes it emit the dev-server URL instead. */
    expect(asset('build/assets/app.js'))->toStartWith('https://');
});

test('a redirect keeps the forwarded scheme', function () {
    $response = $this->get('http://localhost/', ['X-Forwarded-Proto' => 'https']);

    expect($response->headers->get('Location'))->toStartWith('https://');
});
