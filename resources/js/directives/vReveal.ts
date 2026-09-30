import type { Directive } from 'vue';

/*
 * Scroll reveal for the public site (user request 2026-09-26: animate every
 * landing section). `v-reveal` plays an entrance the first time an element
 * enters the viewport, once. The argument picks the motion and the value is
 * a delay in milliseconds, for staggering a list:
 *
 *     <h2 v-reveal>…</h2>
 *     <article v-for="(item, i) in items" v-reveal:zoom="i * 90">…</article>
 *
 * `start` / `end` slide in from the reading direction's start or end side,
 * so they mirror on an Arabic (RTL) page. The hidden state and keyframes are
 * in resources/css/app.css, inside `prefers-reduced-motion: no-preference`:
 * reduced-motion users and print get the static page.
 *
 * The reveal owns `opacity` and `transform` while it plays. Tailwind v4's
 * `translate-*`, `rotate-*` and `scale-*` set their own properties and
 * compose with it, but keep `transition-all` / `transition-opacity` off a
 * revealed element. An element may carry its own `animate-*` utility (a
 * float): the reveal replaces it while it plays and hands back when it ends.
 */
export type RevealMotion =
    | 'up'
    | 'fade'
    | 'zoom'
    | 'start'
    | 'end'
    | 'pop'
    | 'tilt'
    | 'rise';

let observer: IntersectionObserver | null = null;

function show(el: HTMLElement): void {
    el.dataset.revealed = '';
}

function sharedObserver(): IntersectionObserver | null {
    if (typeof IntersectionObserver === 'undefined') {
        return null;
    }

    // Reveal as soon as any part is on screen: a margin that waits for the
    // element to be further in would leave content at the bottom edge of
    // the first screen blank until the visitor scrolls.
    observer ??= new IntersectionObserver((entries, io) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                io.unobserve(entry.target);
                show(entry.target as HTMLElement);
            }
        }
    });

    return observer;
}

/** Drop the reveal once it has played, so nothing replays on a re-layout. */
function finish(event: AnimationEvent): void {
    const el = event.currentTarget as HTMLElement;

    if (event.target !== el || !event.animationName.startsWith('reveal-')) {
        return;
    }

    el.removeEventListener('animationend', finish);
    delete el.dataset.reveal;
    delete el.dataset.revealed;
    el.style.removeProperty('--reveal-delay');
}

export const vReveal: Directive<
    HTMLElement,
    number | undefined,
    string,
    RevealMotion
> = {
    beforeMount(el, { arg, value }) {
        el.dataset.reveal = arg ?? 'up';
        // Always set, so a nested reveal never inherits its parent's delay.
        el.style.setProperty('--reveal-delay', `${value ?? 0}ms`);
        el.addEventListener('animationend', finish);
    },
    mounted(el) {
        const io = sharedObserver();

        if (io) {
            io.observe(el);
        } else {
            show(el);
        }
    },
    beforeUnmount(el) {
        observer?.unobserve(el);
        el.removeEventListener('animationend', finish);
    },
};
