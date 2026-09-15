<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    /** `full` = palm mark + wordmark + tagline; `mark` = palm mark only. */
    variant?: 'full' | 'mark';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'full',
});
</script>

<template>
    <img
        v-if="variant === 'mark'"
        src="/brand/guesvia-mark.png"
        alt="Guesvia"
        width="144"
        height="144"
        draggable="false"
        :class="cn('size-9 shrink-0 object-contain', props.class)"
    />
    <!-- The client's lockup, split into mark / wordmark / tagline so each piece
         sits where the approved mockup places it (mark 58px, wordmark 19px to
         its right, tagline 8px under the wordmark). -->
    <span
        v-else
        role="img"
        aria-label="Guesvia — English for Hotel Staff"
        :class="cn('flex shrink-0 items-start', props.class)"
    >
        <img
            src="/brand/guesvia-mark.png"
            alt=""
            width="144"
            height="144"
            draggable="false"
            class="size-[58px] shrink-0"
        />
        <span class="ms-[18.5px] flex flex-col items-start">
            <img
                src="/brand/guesvia-wordmark.png"
                alt=""
                width="405"
                height="92"
                draggable="false"
                class="-mt-px h-auto w-[136px] max-w-none"
            />
            <img
                src="/brand/guesvia-tagline.png"
                alt=""
                width="429"
                height="47"
                draggable="false"
                class="mt-2 h-auto w-[144px] max-w-none"
            />
        </span>
    </span>
</template>
