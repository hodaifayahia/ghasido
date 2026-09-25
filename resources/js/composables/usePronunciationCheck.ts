import { tryOnScopeDispose } from '@vueuse/core';
import type { Ref } from 'vue';
import { ref } from 'vue';
import { store } from '@/routes/learn/pronunciation';
import type { PronunciationResult } from '@/types';

export type PronunciationTarget = {
    lessonId: number;
    blockId: number;
    /** The sentence index (Listen & Repeat) or lexicon item id. */
    item: number;
    /** One word of the sentence, for a drill. */
    word?: number | null;
};

export type PronunciationRecording = {
    blob: Blob;
    durationMs: number;
    mimeType: string;
};

export type UsePronunciationCheckReturn = {
    result: Ref<PronunciationResult | null>;
    /** Uploading, or waiting for the check or the coach. */
    busy: Ref<boolean>;
    /** A message for the learner; never a provider error. */
    error: Ref<string | null>;
    check: (
        target: PronunciationTarget,
        recording: PronunciationRecording,
    ) => Promise<void>;
    reset: () => void;
};

/** Poll every 800 ms, for at most a minute (PERF-04). */
const POLL_MS = 800;
const MAX_POLLS = 75;

const FALLBACK_ERROR = 'We could not check this recording. Please try again.';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

function extension(mimeType: string): string {
    if (mimeType.includes('mp4')) {
        return 'm4a';
    }

    return mimeType.includes('ogg') ? 'ogg' : 'webm';
}

/*
 * One pronunciation check (spec 0006 §5): upload the recording with the
 * address of what was to be said, then poll the result — the word verdicts
 * first, the coach's tip a moment later — until it is settled. The server
 * keeps the recording (DATA-02); nothing is judged in the browser.
 */
export function usePronunciationCheck(): UsePronunciationCheckReturn {
    const result = ref<PronunciationResult | null>(null);
    const busy = ref(false);
    const error = ref<string | null>(null);

    let timer: ReturnType<typeof setTimeout> | null = null;
    let generation = 0;

    function stopPolling(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function poll(url: string, run: number, count: number): void {
        timer = setTimeout(() => {
            void (async () => {
                if (run !== generation) {
                    return;
                }

                try {
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (run !== generation) {
                        return;
                    }

                    if (response.ok) {
                        result.value =
                            (await response.json()) as PronunciationResult;
                    }
                } catch {
                    // A dropped connection: keep polling (PERF-03).
                }

                if (result.value?.settled || count + 1 >= MAX_POLLS) {
                    busy.value = false;

                    if (!result.value?.settled) {
                        error.value = FALLBACK_ERROR;
                    }

                    return;
                }

                poll(url, run, count + 1);
            })();
        }, POLL_MS);
    }

    async function check(
        target: PronunciationTarget,
        recording: PronunciationRecording,
    ): Promise<void> {
        stopPolling();
        generation += 1;
        const run = generation;

        busy.value = true;
        error.value = null;
        result.value = null;

        const body = new FormData();
        body.append(
            'audio',
            recording.blob,
            `pronunciation.${extension(recording.mimeType)}`,
        );
        body.append('item', String(target.item));
        body.append('duration_ms', String(Math.round(recording.durationMs)));

        if (target.word !== undefined && target.word !== null) {
            body.append('word', String(target.word));
        }

        try {
            const response = await fetch(
                store.url({ lesson: target.lessonId, block: target.blockId }),
                {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                },
            );

            if (run !== generation) {
                return;
            }

            if (!response.ok) {
                const payload = (await response.json().catch(() => ({}))) as {
                    message?: string;
                };

                error.value =
                    response.status === 429 && payload.message
                        ? payload.message
                        : FALLBACK_ERROR;
                busy.value = false;

                return;
            }

            result.value = (await response.json()) as PronunciationResult;

            if (result.value.settled) {
                busy.value = false;

                return;
            }

            poll(result.value.pollUrl, run, 0);
        } catch {
            if (run === generation) {
                error.value = FALLBACK_ERROR;
                busy.value = false;
            }
        }
    }

    function reset(): void {
        stopPolling();
        generation += 1;
        result.value = null;
        error.value = null;
        busy.value = false;
    }

    tryOnScopeDispose(stopPolling);

    return { result, busy, error, check, reset };
}
