/**
 * Minimal service worker.
 *
 * Its job is to make the app installable — Chrome only offers the install
 * prompt when a worker with a fetch handler is registered — and to show a
 * readable page instead of the browser's error when a navigation fails
 * offline. It deliberately caches nothing else: the queue is live data and a
 * stale copy is worse than no copy.
 */

const CACHE_NAME = 'prompt-queue-shell-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE_NAME)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    // Only full page loads get the fallback. Inertia visits are same-origin
    // XHR expecting JSON, and handing them an HTML page would surface as
    // corrupt data rather than as being offline — `mode: 'navigate'` is what
    // separates the two.
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() =>
            caches.match(OFFLINE_URL, { cacheName: CACHE_NAME }),
        ),
    );
});
