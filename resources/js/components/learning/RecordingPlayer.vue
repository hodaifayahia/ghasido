<script setup lang="ts">
import { Pause, Play } from '@lucide/vue';
import { useMediaControls } from '@vueuse/core';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import WaveformGlyph from '@/components/learning/WaveformGlyph.vue';
import { cn } from '@/lib/utils';

/*
 * Plays back one recording (spec 0003 H.2; photo_25 "0:00 / 0:05"): a
 * play/pause button, the static waveform bars and the counter. Works for a
 * just-recorded blob URL and for a stored recording URL alike.
 */
type Props = {
    src: string | null;
    /** Used for the total while the metadata has not loaded yet. */
    durationMs?: number | null;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { durationMs: null });

const audio = ref<HTMLAudioElement>();
const { playing, currentTime, duration } = useMediaControls(audio, {
    src: computed(() => props.src ?? ''),
});

function clock(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

const total = computed(() =>
    Number.isFinite(duration.value) && duration.value > 0
        ? duration.value
        : (props.durationMs ?? 0) / 1000,
);

function toggle(): void {
    playing.value = !playing.value;
}
</script>

<template>
    <div
        :class="
            cn(
                'bg-tint-grid flex min-h-14 items-center gap-3 rounded-md px-3',
                props.class,
            )
        "
    >
        <audio ref="audio" preload="metadata" class="hidden" />
        <button
            type="button"
            :disabled="!src"
            :aria-label="playing ? $t('Pause recording') : $t('Play recording')"
            class="bg-brand-600 hover:bg-brand-700 focus-visible:ring-brand-600/40 grid size-11 shrink-0 place-items-center rounded-full text-white focus-visible:ring-3 focus-visible:outline-none disabled:opacity-50"
            @click="toggle"
        >
            <Pause
                v-if="playing"
                class="size-5 fill-current"
                aria-hidden="true"
            />
            <Play
                v-else
                class="ms-0.5 size-5 fill-current"
                aria-hidden="true"
            />
        </button>
        <WaveformGlyph class="text-brand-400 flex-1" />
        <span class="text-ink text-sm font-semibold tabular-nums">
            {{ clock(currentTime) }} / {{ clock(total) }}
        </span>
    </div>
</template>
