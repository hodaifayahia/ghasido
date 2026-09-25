<script setup lang="ts">
import { Turtle, Volume2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useAudio } from '@/composables/useAudio';
import { cn } from '@/lib/utils';

/*
 * The 🔊 Normal / 🐢 Slow control (CTRL-05, CTRL-06, TTS-01, ACC-03), drawn
 * as desginphotos/employ/photo_2 draws it: `lg` is the labelled 167×52
 * box (normal: brand-50 fill, solid brand-600 speaker, "Normal" 16px
 * semibold brand-700; slow: success-tint fill, solid turtle, "Slow" in
 * success-text); `sm` is the 40px round icon-only button of the side list.
 * Plays a stored clip by URL; with no clip yet the button is disabled with
 * an explanation, never a broken control.
 */
type Props = {
    src: string | null | undefined;
    variant?: 'normal' | 'slow';
    size?: 'lg' | 'sm';
    /** Spoken label for screen readers; the sentence being played. */
    text?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'normal',
    size: 'lg',
    text: undefined,
});

const { play, isPlaying } = useAudio();

const fallbackPlaying = ref(false);
let stopFallback: (() => void) | null = null;

const browserSpeechAvailable = computed(
    () =>
        typeof window !== 'undefined' &&
        'speechSynthesis' in window &&
        typeof SpeechSynthesisUtterance !== 'undefined' &&
        Boolean(props.text?.trim()),
);
const playing = computed(() => isPlaying(props.src) || fallbackPlaying.value);
const disabled = computed(() => !props.src && !browserSpeechAvailable.value);
const label = computed(() => (props.variant === 'slow' ? 'Slow' : 'Normal'));
const ariaLabel = computed(() =>
    props.text
        ? `Play "${props.text}" at ${props.variant} speed`
        : `Play at ${props.variant} speed`,
);
const title = computed(() =>
    disabled.value
        ? 'Audio is unavailable until a speech provider is configured'
        : !props.src
          ? 'Using browser speech while the stored audio is unavailable'
          : undefined,
);

const tone = computed(() =>
    props.variant === 'slow'
        ? 'bg-success-tint text-success-text hover:bg-success/20'
        : 'bg-brand-50 text-brand-700 hover:bg-brand-100',
);

const icon = computed(() =>
    props.variant === 'slow' ? 'text-success' : 'text-brand-600',
);

function playBrowserSpeech(): void {
    if (!browserSpeechAvailable.value || typeof window === 'undefined') {
        return;
    }

    if (fallbackPlaying.value) {
        stopFallback?.();

        return;
    }

    stopFallback?.();

    const synthesis = window.speechSynthesis;
    const utterance = new SpeechSynthesisUtterance(props.text);
    let finished = false;
    const finish = (): void => {
        if (finished) return;

        finished = true;
        fallbackPlaying.value = false;
        if (stopFallback === stop) {
            stopFallback = null;
        }
    };
    const stop = (): void => {
        synthesis.cancel();
        finish();
    };

    utterance.rate = props.variant === 'slow' ? 0.75 : 1;
    utterance.onend = finish;
    utterance.onerror = finish;
    fallbackPlaying.value = true;
    stopFallback = stop;
    synthesis.speak(utterance);
}

function toggleAudio(): void {
    if (props.src) {
        void play(props.src, props.variant === 'slow' ? 0.75 : 1);

        return;
    }

    playBrowserSpeech();
}

onBeforeUnmount(() => {
    if (fallbackPlaying.value) {
        stopFallback?.();
    }
});
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        :aria-label="ariaLabel"
        :aria-pressed="playing"
        :title="title"
        :class="
            cn(
                'ease-brand focus-visible:ring-brand-600/40 inline-flex shrink-0 items-center justify-center transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none',
                tone,
                size === 'lg'
                    ? 'h-[52px] min-w-[167px] gap-3 rounded-md px-6 text-base font-semibold'
                    : 'size-10 min-h-11 min-w-11 rounded-full md:min-h-10 md:min-w-10',
                playing && 'animate-pulse-ring motion-reduce:animate-none',
                props.class,
            )
        "
        @click="toggleAudio"
    >
        <Turtle
            v-if="variant === 'slow'"
            :class="cn('size-[26px] fill-current', icon)"
            aria-hidden="true"
        />
        <Volume2
            v-else
            :class="cn('size-[22px] [&>path:first-child]:fill-current', icon)"
            aria-hidden="true"
        />
        <span v-if="size === 'lg'">{{ label }}</span>
    </button>
</template>
