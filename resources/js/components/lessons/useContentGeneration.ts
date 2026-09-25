import { useIntervalFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import {
    JsonRequestError,
    getJson,
    postJson,
} from '@/components/lessons/lessonsHttp';
import { retry, show } from '@/routes/lesson-generations';
import type { ContentGeneration, ContentGenerationResponse } from '@/types';

/** How often the progress panel asks the server (PERF-04). */
const POLL_MS = 2500;

export type UseContentGenerationReturn = {
    generation: Ref<ContentGeneration | null>;
    error: Ref<string | null>;
    submitting: Ref<boolean>;
    finished: ComputedRef<boolean>;
    failed: ComputedRef<boolean>;
    start: (url: string, body: FormData) => Promise<void>;
    retryGeneration: () => Promise<void>;
    reset: () => void;
};

function messageOf(error: unknown): string {
    if (error instanceof JsonRequestError) {
        const first = Object.values(error.errors)[0];

        return first ?? 'The request failed. Please try again.';
    }

    return 'The request failed. Please try again.';
}

/**
 * Start a "Generate with AI" request and poll its row until it is done or
 * failed (GEN-01, PERF-04; spec 0004). The server row is the truth; nothing
 * here is stored in the browser.
 */
export function useContentGeneration(): UseContentGenerationReturn {
    const generation = ref<ContentGeneration | null>(null);
    const error = ref<string | null>(null);
    const submitting = ref(false);

    const finished = computed(() => generation.value?.state === 'done');
    const failed = computed(() => generation.value?.state === 'failed');

    const poller = useIntervalFn(
        () => {
            void refresh();
        },
        POLL_MS,
        { immediate: false },
    );

    async function refresh(): Promise<void> {
        const current = generation.value;

        if (current === null) {
            poller.pause();

            return;
        }

        try {
            const response = await getJson<ContentGenerationResponse>(
                show.url(current.id),
            );
            generation.value = response.generation;
        } catch (caught) {
            // A dropped connection is retried on the next tick (PERF-03).
            error.value = messageOf(caught);

            return;
        }

        error.value = null;

        if (
            generation.value.state === 'done' ||
            generation.value.state === 'failed'
        ) {
            poller.pause();
        }
    }

    function follow(next: ContentGeneration): void {
        generation.value = next;

        if (next.state === 'done' || next.state === 'failed') {
            poller.pause();
        } else {
            poller.resume();
        }
    }

    async function start(url: string, body: FormData): Promise<void> {
        submitting.value = true;
        error.value = null;

        try {
            const response = await postJson<ContentGenerationResponse>(
                url,
                body,
            );
            follow(response.generation);
        } catch (caught) {
            error.value = messageOf(caught);
        } finally {
            submitting.value = false;
        }
    }

    async function retryGeneration(): Promise<void> {
        const current = generation.value;

        if (current === null) {
            return;
        }

        submitting.value = true;
        error.value = null;

        try {
            const response = await postJson<ContentGenerationResponse>(
                retry.url(current.id),
                new FormData(),
            );
            follow(response.generation);
        } catch (caught) {
            error.value = messageOf(caught);
        } finally {
            submitting.value = false;
        }
    }

    function reset(): void {
        poller.pause();
        generation.value = null;
        error.value = null;
    }

    return {
        generation,
        error,
        submitting,
        finished,
        failed,
        start,
        retryGeneration,
        reset,
    };
}
