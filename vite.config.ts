import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

/*
  Set only inside the Lando container (see .lando.yml). Vite has to bind
  0.0.0.0 there to be reachable through the published port, but the URL it
  advertises to the browser must be one the browser can actually resolve.
  Unset everywhere else, so this whole block is inert for `npm run dev`.
*/
const devServerOrigin = process.env.VITE_DEV_SERVER_ORIGIN;

export default defineConfig({
    server: devServerOrigin
        ? {
              host: '0.0.0.0',
              port: Number(new URL(devServerOrigin).port),
              strictPort: true,
              origin: devServerOrigin,
              hmr: { host: new URL(devServerOrigin).hostname },
          }
        : undefined,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
