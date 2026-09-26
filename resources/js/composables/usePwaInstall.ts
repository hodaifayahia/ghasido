import { computed, ref, shallowRef } from 'vue';
import type { ComputedRef, Ref } from 'vue';

// Chrome, Edge and Samsung Internet fire this before offering to install the
// app; no browser type ships for it yet.
type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export type InstallOutcome = 'accepted' | 'dismissed' | 'unavailable';

export type UsePwaInstallReturn = {
    /** The browser can show its own install dialog right now. */
    canPrompt: ComputedRef<boolean>;
    /** Running as the installed app (home-screen icon), not in a tab. */
    standalone: Ref<boolean>;
    /** iPhone / iPad: install only through Share → Add to Home Screen. */
    isIos: boolean;
    install: () => Promise<InstallOutcome>;
};

// Module state: the event fires once, often before the landing page mounts,
// so it is captured at boot (`initializePwa` in app.ts) and kept here.
const deferredPrompt = shallowRef<BeforeInstallPromptEvent | null>(null);
const standalone = ref(false);

function detectStandalone(): boolean {
    const nav = navigator as Navigator & { standalone?: boolean };

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        nav.standalone === true
    );
}

function detectIos(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    // iPadOS reports itself as a Mac with touch.
    return (
        /iphone|ipad|ipod/i.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

/**
 * Registers the service worker (public/sw.js) and starts listening for the
 * install prompt. Called once from app.ts.
 */
export function initializePwa(): void {
    if (typeof window === 'undefined') {
        return;
    }

    standalone.value = detectStandalone();

    window.addEventListener('beforeinstallprompt', (event) => {
        // Keep the browser's mini-infobar quiet; the landing page's
        // "Download the app" button shows the dialog instead.
        event.preventDefault();
        deferredPrompt.value = event as BeforeInstallPromptEvent;
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt.value = null;
    });

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {
                // Installing is a bonus; the site works without it.
            });
        });
    }
}

export function usePwaInstall(): UsePwaInstallReturn {
    const canPrompt = computed(() => deferredPrompt.value !== null);

    async function install(): Promise<InstallOutcome> {
        const event = deferredPrompt.value;

        if (!event) {
            return 'unavailable';
        }

        await event.prompt();
        const choice = await event.userChoice;
        // A prompt can only be shown once.
        deferredPrompt.value = null;

        return choice.outcome;
    }

    return { canPrompt, standalone, isIos: detectIos(), install };
}
