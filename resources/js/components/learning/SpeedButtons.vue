<script setup lang="ts">
import { Gauge, Turtle } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * "Normal Speed" / "Slower Speed" toggle pair for the video step (spec 0003
 * H.2, photo_6): the same tints as AudioButton, the selected speed carrying
 * the brand ring. The parent applies the rate (useAudio.playbackRate or
 * the <video> element).
 */
type Props = {
    modelValue: 'normal' | 'slow';
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: 'normal' | 'slow'];
}>();

const base =
    'ease-brand focus-visible:ring-brand-600/40 flex h-[52px] flex-1 items-center justify-center gap-3 rounded-md px-5 text-base font-semibold transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] motion-reduce:transition-none';
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Playback speed"
        :class="cn('flex gap-3', props.class)"
    >
        <button
            type="button"
            role="radio"
            :aria-checked="modelValue === 'normal'"
            :class="
                cn(
                    base,
                    'bg-brand-50 text-brand-700 hover:bg-brand-100',
                    modelValue === 'normal' && 'ring-brand-600 ring-2',
                )
            "
            @click="emit('update:modelValue', 'normal')"
        >
            <Gauge class="text-brand-600 size-6" aria-hidden="true" />
            Normal Speed
        </button>
        <button
            type="button"
            role="radio"
            :aria-checked="modelValue === 'slow'"
            :class="
                cn(
                    base,
                    'bg-success-tint text-success-text hover:bg-success/20',
                    modelValue === 'slow' && 'ring-success ring-2',
                )
            "
            @click="emit('update:modelValue', 'slow')"
        >
            <Turtle
                class="text-success size-[26px] fill-current"
                aria-hidden="true"
            />
            Slower Speed
        </button>
    </div>
</template>
