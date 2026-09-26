<script setup lang="ts">
import { Volume2 } from '@lucide/vue';
import { computed } from 'vue';
import WaveformGlyph from '@/components/learning/WaveformGlyph.vue';
import { useAudio } from '@/composables/useAudio';
import { cn } from '@/lib/utils';

/*
 * The prompt player at the top of an option activity (photo_8): a 72px
 * brand speaker, a waveform and "index / total". Plays the stored clip at
 * the chosen speed (CTRL-05); disabled with no clip, never broken.
 */
type Props = {
    src: string | null;
    text?: string;
    index: number;
    total: number;
    rate?: number;
};

const props = withDefaults(defineProps<Props>(), { text: undefined, rate: 1 });

const { play, isPlaying } = useAudio();
const playing = computed(() => isPlaying(props.src));
const disabled = computed(() => !props.src);
</script>

<template>
    <div class="bg-app-alt flex items-center gap-4 rounded-xl px-5 py-4">
        <button
            type="button"
            :disabled="disabled"
            :aria-label="
                text ? $t('Play “:text”', { text }) : $t('Play the prompt')
            "
            :class="
                cn(
                    'bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/30 grid size-[72px] shrink-0 place-items-center rounded-full text-white transition focus-visible:ring-3 focus-visible:outline-none active:scale-95 disabled:opacity-50',
                    playing && 'animate-pulse-ring motion-reduce:animate-none',
                )
            "
            @click="play(src, rate)"
        >
            <Volume2
                class="size-8 [&>path:first-child]:fill-current"
                aria-hidden="true"
            />
        </button>

        <div class="flex min-w-0 flex-1 justify-center overflow-hidden">
            <WaveformGlyph class="text-brand-400 h-9 w-48" />
        </div>

        <span class="text-ink font-heading shrink-0 text-lg font-bold">
            {{ index }} / {{ total }}
        </span>
    </div>
</template>
