import { tryOnScopeDispose, watchDebounced } from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { computed, reactive, ref } from 'vue';
import { JsonRequestError, postJson } from '@/components/lessons/lessonsHttp';
import { t } from '@/lib/i18n';
import { draft, lookup, write } from '@/routes/translations';

/*
 * The meaning of an English field an admin is typing, in any helper
 * language (Arabic, French or one the Super Admin added; client request
 * 2026-10-01). Originally Arabic only (client request
 * 2026-09-29: "Translate meaning to Arabic" above every English field of the
 * test and lesson builders). Meanings are stored per distinct text
 * (`text_translations`, looked up by a hash of the text), so the same store
 * serves every field on the page, and a field whose text changed shows the
 * new text's state. A hand-written meaning is never replaced by the AI.
 */
export type FieldMeaningState =
    | 'missing'
    | 'drafting'
    | 'ai'
    | 'manual'
    | 'failed';

export type FieldMeaning = {
    state: FieldMeaningState;
    arabic: string | null;
};

type Row = {
    text: string;
    translation?: string | null;
    arabic: string | null;
    state: FieldMeaningState;
};

/**
 * The language the builders' translation fields edit, shared by every field
 * on the page: pick French once and every field shows French. Empty until a
 * field sets the user's own helper language as the start.
 */
export const editingLocale = ref('');

export type UseFieldMeaningReturn = {
    /** The stored meaning of the current text; null until looked up. */
    meaning: ComputedRef<FieldMeaning | null>;
    empty: ComputedRef<boolean>;
    busy: Ref<boolean>;
    error: Ref<string>;
    draftWithAi: () => Promise<void>;
    save: (arabic: string) => Promise<boolean>;
    /** Look the text up again (the button opened); follow a running draft. */
    refresh: () => void;
};

const store = reactive(new Map<string, FieldMeaning>());
const queued = new Map<string, { text: string; locale: string }>();
const polling = new Set<string>();
let flushTimer: ReturnType<typeof setTimeout> | null = null;

const POLL_MS = 1500;
const MAX_POLLS = 60;
const BATCH = 150;

export function meaningKey(text: string): string {
    return text.trim().replace(/\s+/gu, ' ').toLowerCase();
}

function storeKey(locale: string, text: string): string {
    return `${locale}|${meaningKey(text)}`;
}

function remember(row: Row, key: string): void {
    store.set(key, {
        state: row.state,
        arabic: row.translation ?? row.arabic,
    });
}

async function flush(): Promise<void> {
    flushTimer = null;

    const batch = [...queued.entries()].slice(0, BATCH);
    batch.forEach(([key]) => queued.delete(key));

    if (queued.size > 0) {
        schedule();
    }

    // One request per language.
    const byLocale = new Map<string, [string, string][]>();
    batch.forEach(([key, { text, locale }]) => {
        byLocale.set(locale, [...(byLocale.get(locale) ?? []), [key, text]]);
    });

    await Promise.all(
        [...byLocale.entries()].map(async ([locale, rows]) => {
            const body = new FormData();
            body.append('language', locale);
            rows.forEach(([, text]) => body.append('texts[]', text));

            try {
                const reply = await postJson<{ items: Row[] }>(
                    lookup.url(),
                    body,
                );
                reply.items.forEach((row, index) => {
                    const key = rows[index]?.[0];

                    if (key !== undefined) {
                        remember(row, key);
                    }
                });
            } catch {
                // The badge stays blank; opening the button looks it up again.
            }
        }),
    );
}

function schedule(): void {
    if (flushTimer === null) {
        flushTimer = setTimeout(() => {
            void flush();
        }, 60);
    }
}

function ask(text: string, locale: string, force = false): void {
    if (meaningKey(text) === '') {
        return;
    }

    const key = storeKey(locale, text);

    if (!force && store.has(key)) {
        return;
    }

    queued.set(key, { text, locale });
    schedule();
}

/** Follow a draft running on the server until it is done or failed. */
function follow(text: string, locale: string): void {
    const key = storeKey(locale, text);

    if (polling.has(key)) {
        return;
    }

    polling.add(key);
    let polls = 0;

    const tick = async (): Promise<void> => {
        polls += 1;
        const body = new FormData();
        body.append('language', locale);
        body.append('texts[]', text);

        try {
            const reply = await postJson<{ items: Row[] }>(lookup.url(), body);
            const row = reply.items[0];

            if (row !== undefined) {
                remember(row, key);
            }
        } catch {
            // A dropped connection gets the next tick (PERF-03).
        }

        if (store.get(key)?.state === 'drafting' && polls < MAX_POLLS) {
            setTimeout(() => {
                void tick();
            }, POLL_MS);

            return;
        }

        if (store.get(key)?.state === 'drafting') {
            store.set(key, { state: 'failed', arabic: null });
        }

        polling.delete(key);
    };

    setTimeout(() => {
        void tick();
    }, POLL_MS);
}

function messageOf(e: unknown): string {
    if (e instanceof JsonRequestError) {
        return (
            e.errors._ ??
            e.errors.message ??
            Object.values(e.errors)[0] ??
            t('The request failed. Please try again.')
        );
    }

    return t('The request failed. Please try again.');
}

export function useFieldMeaning(
    text: () => string,
    locale: () => string = () => 'ar',
): UseFieldMeaningReturn {
    const busy = ref(false);
    const error = ref('');

    const key = computed(() => storeKey(locale(), text()));
    const empty = computed(() => meaningKey(text()) === '');
    const meaning = computed(() => store.get(key.value) ?? null);

    const stop = watchDebounced(
        key,
        () => {
            error.value = '';
            ask(text(), locale());
        },
        { debounce: 400, immediate: true },
    );

    tryOnScopeDispose(stop);

    async function draftWithAi(): Promise<void> {
        const value = text();

        if (meaningKey(value) === '' || busy.value) {
            return;
        }

        busy.value = true;
        error.value = '';

        const language = locale();
        const body = new FormData();
        body.append('language', language);
        body.append('text', value);

        try {
            const reply = await postJson<{ item: Row }>(draft.url(), body);
            remember(reply.item, storeKey(language, value));

            if (reply.item.state === 'drafting') {
                follow(value, language);
            }
        } catch (e) {
            error.value = messageOf(e);
        } finally {
            busy.value = false;
        }
    }

    async function save(arabic: string): Promise<boolean> {
        const value = text();

        if (meaningKey(value) === '' || arabic.trim() === '' || busy.value) {
            return false;
        }

        busy.value = true;
        error.value = '';

        const language = locale();
        const body = new FormData();
        body.append('language', language);
        body.append('text', value);
        body.append('translation', arabic);

        try {
            const reply = await postJson<{ item: Row }>(write.url(), body);
            remember(reply.item, storeKey(language, value));

            return true;
        } catch (e) {
            error.value = messageOf(e);

            return false;
        } finally {
            busy.value = false;
        }
    }

    function refresh(): void {
        const value = text();

        if (meaningKey(value) === '') {
            return;
        }

        if (meaning.value?.state === 'drafting') {
            follow(value, locale());

            return;
        }

        ask(value, locale(), true);
    }

    return { meaning, empty, busy, error, draftWithAi, save, refresh };
}
