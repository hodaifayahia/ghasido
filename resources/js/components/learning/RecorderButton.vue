<script setup lang="ts">
import { Mic, RotateCcw, Square } from '@lucide/vue';
import { computed, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useRecorder } from '@/composables/useRecorder';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';

/*
 * The big round microphone (RESP-05, TEST-07, ACC-03; spec 0003 H.2,
 * desgin/11-components.md §11.6): 96px on phones and by default, 88px on
 * the desktop speaking question (`size="md"`). Idle shows the permission
 * explanation before the browser ever prompts; recording pulses a red ring
 * and counts `0:05 / 0:20`; a denied microphone names the recovery step
 * and never dead-ends the activity (the parent offers the text fallback).
 * Emits the blob so the parent uploads it (DATA-02).
 */
type Props = {
    maxSeconds?: number;
    size?: 'md' | 'lg';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    maxSeconds: 20,
    size: 'lg',
});

const emit = defineEmits<{
    recorded: [
        payload: {
            blob: Blob;
            url: string;
            durationMs: number;
            mimeType: string;
        },
    ];
    reset: [];
    /** The microphone is refused or missing: the parent offers typing. */
    unavailable: [];
}>();

const recorder = useRecorder({ maxSeconds: props.maxSeconds });
const { t } = useI18n();

watch(
    recorder.state,
    (state) => {
        if (
            state === 'denied' ||
            state === 'unsupported' ||
            state === 'error'
        ) {
            emit('unavailable');
        }

        if (
            state === 'recorded' &&
            recorder.blob.value !== null &&
            recorder.url.value !== null
        ) {
            emit('recorded', {
                blob: recorder.blob.value,
                url: recorder.url.value,
                durationMs: recorder.durationMs.value,
                mimeType: recorder.mimeType.value,
            });
        }
    },
    { immediate: true },
);

function clock(ms: number): string {
    const total = Math.floor(ms / 1000);

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

const counter = computed(
    () =>
        `${clock(recorder.elapsedMs.value)} / ${clock(props.maxSeconds * 1000)}`,
);

const hint = computed((): string => {
    switch (recorder.state.value) {
        case 'idle':
            return t(recorder.explanation);
        case 'requesting':
            return t('Waiting for your permission…');
        case 'recording':
            return t('Recording… tap to stop.');
        case 'recorded':
            return t('Recorded. Tap the arrow to record again.');
        case 'denied':
            return t(
                'Microphone access was refused. Allow the microphone for this site in your browser settings, then try again — or type your answer instead.',
            );
        case 'unsupported':
            return t(
                'This browser cannot record audio. Please use Chrome or Safari, or type your answer instead.',
            );
        default:
            return t(
                'Recording failed. Please try again or type your answer instead.',
            );
    }
});

function primary(): void {
    if (recorder.state.value === 'recording') {
        recorder.stop();

        return;
    }

    void recorder.start();
}

function again(): void {
    recorder.reset();
    emit('reset');
}
</script>

<template>
    <div :class="cn('flex flex-col items-center gap-3', props.class)">
        <div class="flex items-center gap-4">
            <button
                type="button"
                :aria-label="
                    recorder.state.value === 'recording'
                        ? $t('Stop recording')
                        : $t('Start recording')
                "
                :aria-pressed="recorder.state.value === 'recording'"
                :disabled="
                    recorder.state.value === 'requesting' ||
                    recorder.state.value === 'unsupported'
                "
                :class="
                    cn(
                        'ease-brand focus-visible:ring-brand-600/40 grid place-items-center rounded-full text-white transition-colors duration-150 focus-visible:ring-4 focus-visible:outline-none active:scale-[.97] disabled:opacity-50 motion-reduce:transition-none',
                        size === 'lg' ? 'size-24' : 'size-24 md:size-22',
                        recorder.state.value === 'recording'
                            ? 'bg-danger animate-pulse-ring motion-reduce:animate-none'
                            : 'bg-brand-600 shadow-btn hover:bg-brand-700',
                    )
                "
                data-test="record-answer-button"
                @click="primary"
            >
                <Square
                    v-if="recorder.state.value === 'recording'"
                    class="size-9 fill-current"
                    aria-hidden="true"
                />
                <Mic v-else class="size-10 stroke-[2]" aria-hidden="true" />
            </button>

            <button
                v-if="recorder.state.value === 'recorded'"
                type="button"
                :aria-label="$t('Record again')"
                class="bg-tint-grid text-ink hover:bg-line focus-visible:ring-brand-600/40 grid size-11 place-items-center rounded-full focus-visible:ring-3 focus-visible:outline-none"
                @click="again"
            >
                <RotateCcw class="size-5" aria-hidden="true" />
            </button>
        </div>

        <p
            class="text-ink font-heading text-lg font-semibold tabular-nums"
            aria-live="polite"
        >
            {{ counter }}
        </p>
        <p
            class="text-ink-slate max-w-sm text-center text-sm"
            aria-live="polite"
        >
            {{ hint }}
        </p>
    </div>
</template>
