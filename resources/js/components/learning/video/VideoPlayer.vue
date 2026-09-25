<script setup lang="ts">
import { Maximize, Pause, Play, Volume2, VolumeX } from '@lucide/vue';
import { useMediaControls } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { MediaRef } from '@/types';

/*
 * The lesson video with a custom control bar (photo_6, PERF-02): play, the
 * time, a seek bar, volume and fullscreen. `speed` drives playbackRate so
 * the Normal / Slower buttons work (1.0 / 0.75). With no video file (the
 * seed ships posters only) the poster shows and the bar renders disabled,
 * never a broken control.
 */
type Props = {
    video: MediaRef | null;
    poster: MediaRef | null;
    speed?: number;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { speed: 1 });

const el = ref<HTMLVideoElement>();
const { playing, currentTime, duration, volume } = useMediaControls(el, {
    src: computed(() => props.video?.url ?? ''),
});

const disabled = computed(() => !props.video);
const muted = computed(() => volume.value === 0);

watch(
    () => props.speed,
    (value) => {
        if (el.value) {
            el.value.playbackRate = value;
        }
    },
);
watch(playing, (value) => {
    if (value && el.value) {
        el.value.playbackRate = props.speed;
    }
});

function clock(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds || 0));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

function toggle(): void {
    if (!disabled.value) {
        playing.value = !playing.value;
    }
}

function onSeek(event: Event): void {
    currentTime.value = Number((event.target as HTMLInputElement).value);
}

function toggleMute(): void {
    volume.value = muted.value ? 1 : 0;
}

function fullscreen(): void {
    void el.value?.requestFullscreen?.();
}
</script>

<template>
    <figure
        :class="
            cn(
                'bg-ink shadow-card relative overflow-hidden rounded-xl',
                props.class,
            )
        "
    >
        <video
            v-if="video"
            ref="el"
            :poster="poster?.url"
            class="h-full w-full object-cover"
            playsinline
        >
            <source :src="video.url" />
        </video>
        <img
            v-else-if="poster"
            :src="poster.url"
            :alt="poster.alt ?? ''"
            loading="lazy"
            decoding="async"
            class="h-full w-full object-cover"
        />
        <div v-else class="bg-app-alt h-full w-full" />

        <button
            v-if="!playing"
            type="button"
            :aria-label="disabled ? 'Video coming soon' : 'Play video'"
            :disabled="disabled"
            class="absolute inset-0 grid place-items-center focus-visible:outline-none disabled:cursor-not-allowed"
            @click="toggle"
        >
            <span
                class="grid size-[72px] place-items-center rounded-full bg-black/55 text-white backdrop-blur-sm transition-transform hover:scale-105 active:scale-95 motion-reduce:transition-none"
            >
                <Play class="ms-1 size-8 fill-current" aria-hidden="true" />
            </span>
        </button>

        <figcaption
            class="absolute inset-x-0 bottom-0 flex items-center gap-3 bg-black/60 px-3 py-2 text-white"
        >
            <button
                type="button"
                :aria-label="playing ? 'Pause' : 'Play'"
                :disabled="disabled"
                class="grid size-9 shrink-0 place-items-center rounded-full hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none disabled:opacity-60"
                @click="toggle"
            >
                <component
                    :is="playing ? Pause : Play"
                    class="size-4 fill-current"
                    aria-hidden="true"
                />
            </button>

            <span class="shrink-0 text-xs tabular-nums">
                {{ clock(currentTime) }} / {{ clock(duration) }}
            </span>

            <input
                type="range"
                min="0"
                :max="Number.isFinite(duration) && duration > 0 ? duration : 0"
                :value="currentTime"
                :disabled="disabled"
                aria-label="Seek"
                class="accent-brand-500 h-1 min-w-0 flex-1 cursor-pointer disabled:cursor-not-allowed"
                @input="onSeek"
            />

            <button
                type="button"
                :aria-label="muted ? 'Unmute' : 'Mute'"
                :disabled="disabled"
                class="grid size-9 shrink-0 place-items-center rounded-full hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none disabled:opacity-60"
                @click="toggleMute"
            >
                <component
                    :is="muted ? VolumeX : Volume2"
                    class="size-4"
                    aria-hidden="true"
                />
            </button>

            <button
                type="button"
                aria-label="Fullscreen"
                :disabled="disabled"
                class="grid size-9 shrink-0 place-items-center rounded-full hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none disabled:opacity-60"
                @click="fullscreen"
            >
                <Maximize class="size-4" aria-hidden="true" />
            </button>
        </figcaption>
    </figure>
</template>
