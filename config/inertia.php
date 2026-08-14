<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    /*
     * Off, deliberately.
     *
     * Nothing builds or runs an SSR bundle — the image runs `npm run build`,
     * not `build:ssr` — so production has always rendered on the client.
     * Only `npm run dev` was pre-rendering, through the Vite plugin, and the
     * shell decides its layout from `useShellBreakpoints`, which a server
     * with no viewport resolves to the widest one. Vue then refuses to
     * rectify the mismatched attributes ("Hydration attribute mismatch … The
     * DOM will not be rectified"), so a narrow window loaded the desktop
     * layout's widths and kept them.
     *
     * There is nothing to gain here either: every screen is behind auth, so
     * there is no crawler or first-paint case to serve. Turning this on again
     * means making the shell's breakpoints server-renderable first — CSS
     * media queries rather than JS ones.
     */
    'ssr' => [
        'enabled' => false,
        'url' => 'http://127.0.0.1:13714',
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
