import { watchDebounced } from '@vueuse/core';
import type { ComputedRef } from 'vue';
import { computed, reactive } from 'vue';
import { sendJson } from '@/components/lessons/lessonsHttp';
import { lookup, save as saveMeaning } from '@/routes/translations';

/*
 * The builders' Translation buttons (user request 2026-09-26): the admin
 * writes the Arabic of a lesson or test text right next to its field, and
 * the learner's Show Meaning button reads that same meaning (CTRL-01..03).
 *
 * Meanings are keyed by the English text, exactly as the server keys them
 * (TextTranslation::normalise + lower case), so a field and the learner's
 * page always find the same row. Every button on a screen shares one cache,
 * and their lookups go out together in one request.
 */
export type FieldMeaningState =
    | 'missing'
    | 'drafting'
    | 'failed'
    | 'ai'
    | 'manual';

export type FieldMeaning = { arabic: string | null; state: FieldMeaningState };

type Row = { text: string; arabic: string | null; state: FieldMeaningState };

export type UseFieldMeaningReturn = {
    /** Null while the lookup is on its way. */
    meaning: ComputedRef<FieldMeaning | null>;
    /** False for an empty text or one with no letters: nothing to translate. */
    translatable: ComputedRef<boolean>;
    save: (arabic: string) => Promise<void>;
};

const cache = reactive(new Map<string, FieldMeaning>());
const queued = new Map<string, string>();
let timer: ReturnType<typeof setTimeout> | null = null;

export function meaningKey(text: string): string {
    return text.trim().replace(/\s+/gu, ' ').toLowerCase();
}

function hasLetters(text: string): boolean {
    return /\p{L}/u.test(text);
}

async function flush(): Promise<void> {
    timer = null;
    const batch = [...queued.entries()].slice(0, 200);

    for (const [key] of batch) {
        queued.delete(key);
    }

    if (batch.length === 0) {
        return;
    }

    try {
        const reply = await sendJson<{ items: Row[] }>('POST', lookup.url(), {
            texts: batch.map(([, text]) => text),
        });

        reply.items.forEach((row, index) => {
            const key = batch[index]?.[0];

            if (key !== undefined) {
                cache.set(key, { arabic: row.arabic, state: row.state });
            }
        });
    } catch {
        // A failed lookup leaves the buttons neutral; opening one retries.
    }

    if (queued.size > 0) {
        schedule();
    }
}

function schedule(): void {
    if (timer === null) {
        timer = setTimeout(() => {
            void flush();
        }, 60);
    }
}

function request(text: string): void {
    const key = meaningKey(text);

    if (key === '' || !hasLetters(key) || cache.has(key)) {
        return;
    }

    queued.set(key, text);
    schedule();
}

export function useFieldMeaning(
    text: () => string | null | undefined,
): UseFieldMeaningReturn {
    const current = computed(() => text() ?? '');
    const key = computed(() => meaningKey(current.value));
    const translatable = computed(
        () => key.value !== '' && hasLetters(key.value),
    );

    // Typing changes the text on every key; ask once the admin pauses.
    watchDebounced(current, (value) => request(value), {
        debounce: 400,
        immediate: true,
    });

    async function save(arabic: string): Promise<void> {
        const row = await sendJson<Row>('PUT', saveMeaning.url(), {
            text: current.value,
            arabic,
        });

        cache.set(key.value, { arabic: row.arabic, state: row.state });
    }

    return {
        meaning: computed(() => cache.get(key.value) ?? null),
        translatable,
        save,
    };
}
