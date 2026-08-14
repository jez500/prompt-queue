---
paths:
  - public/sw.js
---

# Public

## The service worker only handles navigations
`public/sw.js` exists to make the app installable (Chrome only offers the
install prompt when a worker with a fetch handler is registered) and to serve
`public/offline.html` when a page load fails. It caches nothing else on
purpose — the queue is live data and a stale copy is worse than none.

The fetch handler must keep its `event.request.mode !== 'navigate'` early
return. Inertia visits are same-origin XHR expecting JSON; answering one with
the HTML offline page surfaces as corrupt data, not as being offline.

It is registered from `resources/js/lib/serviceWorker.ts` under
`import.meta.env.PROD` only — a worker intercepting navigations while Vite
serves over HMR makes "my change didn't show up" a routine puzzle.
