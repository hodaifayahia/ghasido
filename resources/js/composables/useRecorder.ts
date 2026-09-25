import {
    tryOnScopeDispose,
    useIntervalFn,
    usePermission,
    useSupported,
    useUserMedia,
} from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { ref } from 'vue';

export type RecorderState =
    | 'idle'
    | 'requesting'
    | 'recording'
    | 'recorded'
    | 'denied'
    | 'unsupported'
    | 'error';

export type UseRecorderOptions = {
    /** Recording stops by itself at this length (the activity's cap). */
    maxSeconds?: number;
};

export type UseRecorderReturn = {
    state: Ref<RecorderState>;
    supported: ComputedRef<boolean>;
    /** Shown before the browser prompt, so the request is never a surprise. */
    explanation: string;
    elapsedMs: Ref<number>;
    durationMs: Ref<number>;
    blob: Ref<Blob | null>;
    url: Ref<string | null>;
    mimeType: Ref<string>;
    start: () => Promise<void>;
    stop: () => void;
    reset: () => void;
};

const CANDIDATE_TYPES = [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/mp4',
    'audio/ogg;codecs=opus',
    'audio/ogg',
];

/*
 * Microphone capture for speaking answers and role-play turns (RESP-05,
 * TEST-07). The permission is requested on an explicit tap, never on mount;
 * a denial lands in `denied` with the recovery text, never a dead end. The
 * blob is the caller's to upload (learn.recordings.store): a recording that
 * only lives in the browser is a bug (DATA-02).
 */
export function useRecorder(
    options: UseRecorderOptions = {},
): UseRecorderReturn {
    const state = ref<RecorderState>('idle');
    const elapsedMs = ref(0);
    const durationMs = ref(0);
    const blob = ref<Blob | null>(null);
    const url = ref<string | null>(null);
    const mimeType = ref('');

    const supported = useSupported(
        () =>
            typeof MediaRecorder !== 'undefined' &&
            typeof navigator !== 'undefined' &&
            typeof navigator.mediaDevices?.getUserMedia === 'function',
    );
    const permission = usePermission('microphone');

    const media = useUserMedia({
        constraints: { audio: true, video: false },
    });

    let recorder: MediaRecorder | null = null;
    let chunks: Blob[] = [];
    let startedAt = 0;

    const ticker = useIntervalFn(
        () => {
            elapsedMs.value = Date.now() - startedAt;

            if (
                options.maxSeconds !== undefined &&
                elapsedMs.value >= options.maxSeconds * 1000
            ) {
                stop();
            }
        },
        100,
        { immediate: false },
    );

    function releaseUrl(): void {
        if (url.value !== null) {
            URL.revokeObjectURL(url.value);
            url.value = null;
        }
    }

    async function start(): Promise<void> {
        if (!supported.value) {
            state.value = 'unsupported';

            return;
        }

        if (permission.value === 'denied') {
            state.value = 'denied';

            return;
        }

        state.value = 'requesting';
        releaseUrl();
        blob.value = null;

        try {
            await media.start();
        } catch {
            state.value = 'denied';

            return;
        }

        const stream = media.stream.value;

        if (!stream) {
            state.value = 'denied';

            return;
        }

        const type = CANDIDATE_TYPES.find((candidate) =>
            MediaRecorder.isTypeSupported(candidate),
        );

        try {
            recorder = type
                ? new MediaRecorder(stream, { mimeType: type })
                : new MediaRecorder(stream);
        } catch {
            state.value = 'error';
            media.stop();

            return;
        }

        mimeType.value = recorder.mimeType || type || 'audio/webm';
        chunks = [];

        recorder.addEventListener('dataavailable', (event: BlobEvent) => {
            if (event.data.size > 0) {
                chunks.push(event.data);
            }
        });

        recorder.addEventListener('stop', () => {
            ticker.pause();
            durationMs.value = Date.now() - startedAt;
            elapsedMs.value = durationMs.value;
            blob.value = new Blob(chunks, { type: mimeType.value });
            url.value = URL.createObjectURL(blob.value);
            state.value = 'recorded';
            media.stop();
        });

        recorder.start();
        startedAt = Date.now();
        elapsedMs.value = 0;
        state.value = 'recording';
        ticker.resume();
    }

    function stop(): void {
        if (recorder !== null && recorder.state !== 'inactive') {
            recorder.stop();
        }
    }

    function reset(): void {
        stop();
        ticker.pause();
        releaseUrl();
        blob.value = null;
        elapsedMs.value = 0;
        durationMs.value = 0;
        state.value = 'idle';
    }

    tryOnScopeDispose(() => {
        stop();
        ticker.pause();
        media.stop();
        releaseUrl();
    });

    return {
        state,
        supported,
        explanation:
            'We need your microphone to record your answer. Your browser will ask for permission.',
        elapsedMs,
        durationMs,
        blob,
        url,
        mimeType,
        start,
        stop,
        reset,
    };
}
