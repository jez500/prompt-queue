/**
 * Register the service worker that backs installability and the offline page.
 *
 * Production only, deliberately: a worker intercepting navigations while Vite
 * is serving over HMR turns "my change didn't show up" into a routine puzzle.
 */
export function registerServiceWorker(): void {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('Service worker registration failed', error);
        });
    });
}
