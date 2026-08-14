<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WebManifestController extends Controller
{
    /**
     * Serve the web app manifest that makes the queue installable.
     *
     * This is a route rather than a static file in `public/` so the name tracks
     * `config('app.name')` and the icon list can be asserted against the files
     * that `npm run icons` actually wrote.
     *
     * `theme_color` carries a single value while the app has two palettes, so
     * the blade shell also emits paired `theme-color` metas — see app.blade.php.
     */
    public function __invoke(): JsonResponse
    {
        $name = config('app.name', 'Prompt Queue');

        return response()->json([
            'name' => $name,
            'short_name' => $name,
            'description' => 'Queue, order and work through your prompts.',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'theme_color' => '#08080a',
            'background_color' => '#08080a',
            'icons' => [
                [
                    'src' => '/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ])->withHeaders([
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
