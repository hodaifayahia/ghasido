import { tryOnScopeDispose } from '@vueuse/core';
import type { InjectionKey, Ref } from 'vue';
import { inject, ref } from 'vue';
import { useHelperLanguage } from '@/composables/useHelperLanguage';
import { meaning as meaningRoute } from '@/routes';

/*
 * Show Meaning for any English text (CTRL-01..03; client decision
 * 2026-09-26). The Arabic is never in the page: a tap asks the server for
 * the translation made when the content was written (an AI draft or the
 * admin's own text; a tap never calls the AI). While a fresh draft is still
 * on its way the button polls (PERF-04); a text nobody has translated yet
 * says so. A test page switches the whole
 * mechanism off through MEANING_ENABLED when its admin chose so (CTRL-04),
 * and the server refuses the request then too.
 */
export const MEANING_ENABLED: InjectionKey<Ref<boolean> | boolean> =
    Symbol('meaning-enabled');

export type MeaningState = 'idle' | 'loading' | 'ready' | 'missing' | 'error';

export type UseMeaningReturn = {
    shown: Ref<boolean>;
    state: Ref<MeaningState>;
    /** The meaning, in the learner's helper language (named before French). */
    arabic: Ref<string | null>;
    enabled: () => boolean;
    toggle: () => void;
    retry: () => void;
};

const POLL_MS = 1200;
const MAX_POLLS = 40;

/** One answer per text for the whole visit, shared by every button. */
const cache = new Map<string, string>();

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

let cacheLanguage = '';

/** Per helper language: switching language starts a fresh cache. */
function keyOf(text: string): string {
    return `${cacheLanguage}|${text.trim().replace(/\s+/gu, ' ').toLowerCase()}`;
}

/** `text` is in the learner's helper language (client request 2026-09-30). */
type MeaningReply = {
    status: string;
    text: string | null;
    locale?: string;
    dir?: string;
};

async function ask(text: string): Promise<MeaningReply> {
    const response = await fetch(meaningRoute.url(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ text }),
    });

    if (!response.ok) {
        throw new Error(`meaning ${response.status}`);
    }

    return (await response.json()) as MeaningReply;
}

export function useMeaning(text: () => string): UseMeaningReturn {
    const shown = ref(false);
    const state = ref<MeaningState>('idle');
    const arabic = ref<string | null>(null);
    const injected = inject(MEANING_ENABLED, true);
    const helper = useHelperLanguage();

    let timer: ReturnType<typeof setTimeout> | null = null;
    let run = 0;

    function enabled(): boolean {
        const allowed =
            typeof injected === 'boolean' ? injected : injected.value;

        // Only English has an Arabic meaning. Text with no Latin letters has
        // nothing to reveal: the interface label already in Arabic when the
        // Arabic interface is on (I18N-02), a number, a symbol.
        return allowed && /[A-Za-z]/.test(text());
    }

    function stop(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    async function load(current: number, polls: number): Promise<void> {
        const value = text();

        try {
            const reply = await ask(value);

            if (current !== run) {
                return;
            }

            if (reply.status === 'done' && reply.text) {
                cache.set(keyOf(value), reply.text);
                arabic.value = reply.text;
                state.value = 'ready';

                return;
            }

            if (reply.status === 'missing') {
                state.value = 'missing';

                return;
            }

            if (reply.status === 'failed' || polls + 1 >= MAX_POLLS) {
                state.value = 'error';

                return;
            }
        } catch {
            if (current !== run) {
                return;
            }

            // A dropped connection gets a few more tries (PERF-03).
            if (polls + 1 >= MAX_POLLS) {
                state.value = 'error';

                return;
            }
        }

        timer = setTimeout(() => {
            void load(current, polls + 1);
        }, POLL_MS);
    }

    function toggle(): void {
        if (!enabled()) {
            shown.value = false;

            return;
        }

        shown.value = !shown.value;

        if (cacheLanguage !== helper.code.value) {
            cacheLanguage = helper.code.value;
            state.value = 'idle';
        }

        if (!shown.value || state.value === 'ready') {
            return;
        }

        const cached = cache.get(keyOf(text()));

        if (cached) {
            arabic.value = cached;
            state.value = 'ready';

            return;
        }

        if (state.value === 'loading') {
            return;
        }

        stop();
        run += 1;
        state.value = 'loading';
        void load(run, 0);
    }

    function retry(): void {
        stop();
        run += 1;
        shown.value = true;
        state.value = 'loading';
        void load(run, 0);
    }

    tryOnScopeDispose(() => {
        run += 1;
        stop();
    });

    return { shown, state, arabic, enabled, toggle, retry };
}
