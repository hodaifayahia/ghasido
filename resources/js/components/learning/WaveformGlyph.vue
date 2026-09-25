<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * The static waveform bars the practice cards draw next to a speaker
 * (desginphotos/employ/photo_7, photo_8): 13 rounded bars of varying
 * height in currentColor. Decorative: always aria-hidden.
 */
type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const heights = [6, 12, 20, 14, 24, 10, 18, 24, 12, 20, 8, 14, 6];
</script>

<template>
    <svg
        viewBox="0 0 64 24"
        width="64"
        height="24"
        fill="currentColor"
        aria-hidden="true"
        :class="cn('shrink-0', props.class)"
    >
        <rect
            v-for="(h, index) in heights"
            :key="index"
            :x="index * 5"
            :y="(24 - h) / 2"
            width="3"
            :height="h"
            rx="1.5"
        />
    </svg>
</template>
