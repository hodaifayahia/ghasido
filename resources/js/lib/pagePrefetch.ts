import { router } from '@inertiajs/vue3';

// The page name opens every Inertia page response: {"component":"admin\/Tests",…
const COMPONENT = /^\{"component":("(?:[^"\\]|\\.)*")/;

/**
 * Faster page changes. The sidebar links prefetch their page on hover
 * (NavMain), which caches only the server's answer; the page's own
 * JavaScript would still download after the click. So when a prefetch
 * lands, its page code is fetched too, and the click shows the page at once.
 *
 * Inertia keeps prefetched pages until they expire, even after a change, so
 * every write (any non-GET visit) drops them: a stale copy is never shown.
 */
export function initializePagePrefetch(): void {
    router.on('prefetched', (event) => {
        const match = COMPONENT.exec(event.detail.response.data);

        if (match === null) {
            return;
        }

        router.resolveComponent(JSON.parse(match[1]) as string).catch(() => {
            // A failed early download is retried by the visit itself.
        });
    });

    router.on('finish', (event) => {
        if (event.detail.visit.method !== 'get') {
            router.flushAll();
        }
    });
}
